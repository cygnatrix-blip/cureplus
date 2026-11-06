<?php

namespace Tests\Feature;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Jobs\SendMessage;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\DoctorFee;
use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\Billing\BillingService;
use App\Services\VideoPayments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UpiPaymentTest extends TestCase
{
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('next monday 10:00');
    }

    /**
     * A practice with no gateway that takes UPI, a doctor charging ₹500, and a patient who has a portal account.
     *
     * @return array{Organization, User, Location, User, Patient, PatientAccount}
     */
    private function clinic(string $inPerson = 'required', string $online = 'required'): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        DoctorProfile::query()->create(['user_id' => $doctor->id, 'video_link' => 'https://meet.google.com/abc-defg-hij']);
        DoctorAvailability::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'weekday' => $this->monday->dayOfWeek, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 15]);
        DoctorFee::withoutTenancy()->create(['organization_id' => $organization->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'amount_paise' => 50000]);
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'upi', 'is_enabled' => true, 'config' => ['vpa' => 'drroy@okicici', 'payee_name' => 'Dr Roy Clinic', 'in_person' => $inPerson, 'online' => $online]]);

        $account = PatientAccount::factory()->create();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test', 'patient_account_id' => $account->id]);

        return [$organization, $owner, $location, $doctor, $patient, $account];
    }

    private function book(Patient $patient, User $doctor, Location $location, AppointmentMode $mode = AppointmentMode::InPerson, ?CarbonImmutable $at = null): Appointment
    {
        return app(AppointmentService::class)->bookSlot($patient, $doctor->id, $location->id, $at ?? $this->monday, VisitType::New, 'link', $mode);
    }

    private function submit(PatientAccount $account, Appointment $appointment, string $utr): TestResponse
    {
        return $this->actingAs($account, 'patient')
            ->from(route('patient.appointments.show', $appointment->id))
            ->post(route('patient.appointments.upi', $appointment->id), ['utr' => $utr]);
    }

    public function test_a_required_clinic_visit_is_held_and_shows_a_upi_qr_for_the_fee(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic();

        $appointment = $this->book($patient, $doctor, $location);

        $this->assertTrue($appointment->isAwaitingPayment());
        $this->actingAs($account, 'patient')->get(route('patient.appointments.show', $appointment->id))
            ->assertOk()
            ->assertSee('Pay to confirm your appointment')
            ->assertSee('Pay ₹500.00 by UPI')
            ->assertSee('drroy@okicici')
            ->assertSee('upi://pay?pa=drroy%40okicici&amp;pn=Dr%20Roy%20Clinic&amp;am=500.00&amp;cu=INR&amp;tn=Appointment%20'.$appointment->id, false)
            ->assertSee('<svg', false);
    }

    public function test_a_sent_transaction_id_waits_for_the_clinic_and_keeps_the_time_held(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);

        $this->submit($account, $appointment, '4123 5678 9012')->assertRedirect()->assertSessionHas('success', 'Thank you. The clinic will check your payment and confirm it.');

        $payment = Payment::withoutTenancy()->sole();
        $this->assertSame(Payment::UNVERIFIED, $payment->status);
        $this->assertSame('412356789012', $payment->reference);
        $this->assertSame(50000, $payment->amount_paise);
        $this->assertSame('unpaid', Invoice::withoutTenancy()->sole()->status);

        $this->travel(31)->minutes();
        $this->assertSame(0, app(VideoPayments::class)->releaseUnpaid());
        $this->assertTrue($appointment->refresh()->isAwaitingPayment());
        $this->get(route('patient.appointments.show', $appointment->id))->assertSee('Payment sent to the clinic')->assertDontSee('I have paid');
    }

    public function test_staff_confirming_the_payment_marks_the_bill_paid_and_confirms_the_booking(): void
    {
        [, $owner, $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');
        $payment = Payment::withoutTenancy()->sole();
        $invoice = Invoice::withoutTenancy()->sole();

        $this->actingAs($owner, 'web')->get(route('app.invoices.show', $invoice))->assertOk()->assertSee('UPI payment to check')->assertSee('412356789012');
        $this->post(route('app.invoices.upi.confirm', [$invoice, $payment]))->assertRedirect()->assertSessionHas('success', 'UPI payment confirmed.');

        $this->assertSame('captured', $payment->refresh()->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertFalse($appointment->refresh()->isAwaitingPayment());
    }

    public function test_a_video_call_paid_by_upi_gets_its_link_only_after_the_clinic_confirms(): void
    {
        [, $owner, $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location, AppointmentMode::Online);
        $this->submit($account, $appointment, '412356789012');

        $this->assertFalse($appointment->refresh()->canJoinVideo());

        $this->actingAs($owner, 'web')->post(route('app.invoices.upi.confirm', [Invoice::withoutTenancy()->sole(), Payment::withoutTenancy()->sole()]));

        $this->assertTrue($appointment->refresh()->canJoinVideo());
    }

    public function test_a_payment_the_clinic_cannot_find_is_rejected_and_the_patient_is_told(): void
    {
        [, $owner, $location, $doctor, $patient, $account] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');
        $payment = Payment::withoutTenancy()->sole();
        Queue::fake();

        $this->actingAs($owner, 'web')->post(route('app.invoices.upi.reject', [Invoice::withoutTenancy()->sole(), $payment]))->assertRedirect();

        $this->assertSame(Payment::REJECTED, $payment->refresh()->status);
        $this->assertSame('unpaid', Invoice::withoutTenancy()->sole()->status);
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'upi_payment_not_found' && $job->variables['amount'] === '₹500.00');
        $this->actingAs($account, 'patient')->get(route('patient.appointments.show', $appointment->id))->assertSee('could not find your payment with transaction ID 412356789012');
    }

    public function test_a_transaction_id_can_be_used_only_once(): void
    {
        [, $owner, $location, $doctor, $patient, $account] = $this->clinic();
        $first = $this->book($patient, $doctor, $location);
        $second = $this->book($patient, $doctor, $location, at: $this->monday->addMinutes(15));
        $this->submit($account, $first, '412356789012');
        $this->actingAs($owner, 'web')->post(route('app.invoices.upi.reject', [Invoice::withoutTenancy()->sole(), Payment::withoutTenancy()->sole()]));

        $this->submit($account, $first, '412356789012')->assertSessionHasErrors(['utr' => 'This UPI transaction ID has already been used.']);
        $this->submit($account, $second, '412356789012')->assertSessionHasErrors(['utr' => 'This UPI transaction ID has already been used.']);

        $this->assertSame(1, Payment::withoutTenancy()->count());
    }

    public function test_a_second_transaction_id_is_refused_while_one_is_being_checked(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');

        $this->submit($account, $appointment, '999988887777')->assertSessionHasErrors(['utr' => 'The clinic is already checking a payment for this bill.']);

        $this->assertSame(1, Payment::withoutTenancy()->count());
    }

    public function test_a_transaction_id_must_be_twelve_digits(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);

        $this->submit($account, $appointment, '12345')->assertSessionHasErrors(['utr' => 'The UPI transaction ID is the 12-digit number in your UPI app, also called UTR or reference number.']);

        $this->assertSame(0, Payment::withoutTenancy()->count());
    }

    public function test_an_unpaid_clinic_visit_is_released_with_a_clinic_visit_message(): void
    {
        [, , $location, $doctor, $patient] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        $appointment = $this->book($patient, $doctor, $location);
        Queue::fake();
        $this->travel(31)->minutes();

        $this->assertSame(1, app(VideoPayments::class)->releaseUnpaid());

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'appointment_payment_missed');
    }

    public function test_optional_upi_confirms_the_booking_at_once_and_still_offers_to_pay(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic(inPerson: 'optional');

        $appointment = $this->book($patient, $doctor, $location);

        $this->assertFalse($appointment->isAwaitingPayment());
        $this->actingAs($account, 'patient')->get(route('patient.appointments.show', $appointment->id))->assertSee('You can pay now with any UPI app, or pay at the clinic.');
    }

    public function test_when_upi_is_off_for_clinic_visits_nothing_is_offered_or_accepted(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic(inPerson: 'off');

        $appointment = $this->book($patient, $doctor, $location);

        $this->assertFalse($appointment->isAwaitingPayment());
        $this->actingAs($account, 'patient')->get(route('patient.appointments.show', $appointment->id))->assertDontSee('by UPI');
        $this->submit($account, $appointment, '412356789012')->assertNotFound();
    }

    public function test_a_connected_gateway_takes_over_from_upi(): void
    {
        [$organization, , $location, $doctor, $patient, $account] = $this->clinic();
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'cashfree', 'is_enabled' => true, 'config' => ['key_id' => 'id', 'key_secret' => 'secret', 'mode' => 'test']]);

        $appointment = $this->book($patient, $doctor, $location);

        $this->assertFalse($appointment->isAwaitingPayment(), 'A gateway only holds video calls.');
        $this->actingAs($account, 'patient')->get(route('patient.appointments.show', $appointment->id))->assertDontSee('drroy@okicici');
    }

    public function test_another_patient_cannot_send_a_payment_for_someone_elses_appointment(): void
    {
        [, , $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);

        $this->submit(PatientAccount::factory()->create(), $appointment, '412356789012')->assertNotFound();

        $this->assertSame(0, Payment::withoutTenancy()->count());
    }

    public function test_staff_of_another_practice_cannot_confirm_the_payment(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');
        [, $otherOwner] = $this->organizationWithOwner();

        $this->actingAs($otherOwner, 'web')->post(route('app.invoices.upi.confirm', [Invoice::withoutTenancy()->sole(), Payment::withoutTenancy()->sole()]))->assertNotFound();

        $this->assertSame(Payment::UNVERIFIED, Payment::withoutTenancy()->sole()->status);
    }

    public function test_a_doctor_without_billing_rights_cannot_confirm_the_payment(): void
    {
        [, , $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');

        $this->actingAs($doctor, 'web')->post(route('app.invoices.upi.confirm', [Invoice::withoutTenancy()->sole(), Payment::withoutTenancy()->sole()]))->assertForbidden();

        $this->assertSame(Payment::UNVERIFIED, Payment::withoutTenancy()->sole()->status);
    }

    public function test_confirming_is_refused_when_the_bill_was_paid_another_way_meanwhile(): void
    {
        [, $owner, $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');
        $invoice = Invoice::withoutTenancy()->sole();
        $this->actingAs($owner, 'web');
        app(BillingService::class)->recordPayment($invoice, 50000, 'cash');

        $this->post(route('app.invoices.upi.confirm', [$invoice, Payment::withoutTenancy()->where('mode', 'upi')->sole()]))
            ->assertSessionHas('error', 'This bill has changed since the patient paid. Mark this payment as not received, and record what you got with Take payment.');

        $this->assertSame(50000, $invoice->refresh()->paid_paise);
    }

    public function test_a_bill_with_a_payment_to_check_cannot_be_cancelled(): void
    {
        [, $owner, $location, $doctor, $patient, $account] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $this->submit($account, $appointment, '412356789012');
        $invoice = Invoice::withoutTenancy()->sole();

        $this->actingAs($owner, 'web')->post(route('app.invoices.cancel', $invoice), ['reason' => 'Duplicate'])
            ->assertSessionHas('error', 'The patient sent a UPI payment for this bill. Check it before cancelling.');

        $this->assertSame('unpaid', $invoice->refresh()->status);
    }

    public function test_the_owner_saves_upi_settings_and_a_malformed_upi_id_is_refused(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $settings = ['payee_name' => 'Dr Roy', 'online' => 'required', 'in_person' => 'optional', 'is_enabled' => '1'];

        $this->actingAs($owner, 'web')->post(route('app.integrations.upi'), $settings + ['vpa' => 'not a upi id'])
            ->assertSessionHasErrors(['vpa' => 'This does not look like a UPI ID. It looks like name@bank, for example drroy@okicici.']);
        $this->post(route('app.integrations.upi'), $settings + ['vpa' => ' DrRoy@OKICICI '])->assertSessionHasNoErrors();

        $integration = OrganizationIntegration::withoutTenancy()->where('organization_id', $organization->id)->where('kind', 'upi')->sole();
        $this->assertTrue($integration->is_enabled);
        $this->assertSame(['vpa' => 'drroy@okicici', 'payee_name' => 'Dr Roy', 'online' => 'required', 'in_person' => 'optional'], $integration->config);
    }

    public function test_a_receptionist_cannot_change_upi_settings(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web')->post(route('app.integrations.upi'), ['vpa' => 'thief@okaxis', 'payee_name' => 'X', 'online' => 'off', 'in_person' => 'off', 'is_enabled' => '1'])->assertForbidden();

        $this->assertSame(0, OrganizationIntegration::withoutTenancy()->where('kind', 'upi')->count());
    }
}
