<?php

namespace Tests\Feature;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationStatus;
use App\Enums\VisitType;
use App\Jobs\SendMessage;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DoctorAvailability;
use App\Models\DoctorFee;
use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\Billing\BillingService;
use App\Services\DoctorVerification;
use App\Services\Messaging\ReminderScheduler;
use App\Services\OrganizationVerification;
use App\Services\VideoPayments;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoPaymentAndAlliedTest extends TestCase
{
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('next monday 10:00');
    }

    /**
     * @return array{Organization, Location, User, Patient}
     */
    private function clinic(bool $hasGateway = true, int $feePaise = 50000): array
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        DoctorProfile::query()->create(['user_id' => $doctor->id]);
        DoctorAvailability::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'weekday' => $this->monday->dayOfWeek, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 15]);
        DoctorFee::withoutTenancy()->create(['organization_id' => $organization->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'amount_paise' => $feePaise]);

        if ($hasGateway) {
            OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'cashfree', 'is_enabled' => true, 'config' => ['key_id' => 'id', 'key_secret' => 'secret', 'mode' => 'test']]);
        }

        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'mobile' => '+919812345678', 'email' => 'p@example.test']);

        return [$organization, $location, $doctor, $patient];
    }

    private function bookOnline(Patient $patient, User $doctor, Location $location, string $source = 'link'): Appointment
    {
        return app(AppointmentService::class)->bookSlot($patient, $doctor->id, $location->id, $this->monday, VisitType::New, $source, AppointmentMode::Online);
    }

    private function invoiceFor(Appointment $appointment, Organization $organization, Patient $patient): Invoice
    {
        return app(BillingService::class)->createInvoice($organization, $patient, [['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => (int) $appointment->location_id, 'appointment_id' => $appointment->id]);
    }

    private function payOnline(Invoice $invoice): void
    {
        Payment::withoutTenancy()->create(['organization_id' => $invoice->organization_id, 'invoice_id' => $invoice->id, 'amount_paise' => $invoice->total_paise, 'mode' => 'online', 'status' => 'pending', 'gateway_link_id' => 'link-'.$invoice->id]);

        app(BillingService::class)->captureGatewayPayment((int) $invoice->organization_id, 'link-'.$invoice->id, 'pay_1');
    }

    public function test_a_self_booked_video_call_is_held_until_paid_and_the_link_stays_hidden(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $account = PatientAccount::factory()->create();
        $patient->forceFill(['patient_account_id' => $account->id])->save();
        $this->clinicsHaveOwnEmail();
        Queue::fake();

        $appointment = $this->bookOnline($patient, $doctor, $location);

        $this->assertTrue($appointment->isAwaitingPayment());
        $this->assertFalse($appointment->canJoinVideo());
        Queue::assertNothingPushed();
        $this->actingAs($account, 'patient')->get(route('patient.appointments.show', $appointment->id))->assertOk()->assertSee('Pay to confirm your video call')->assertDontSee('Join video call')->assertDontSee((string) $appointment->video_link, false);

        $this->payOnline($this->invoiceFor($appointment, $organization, $patient));

        $this->assertFalse($appointment->refresh()->isAwaitingPayment());
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'video_consult' && $job->variables['link'] === $appointment->video_link);
        $this->get(route('patient.appointments.show', $appointment->id))->assertOk()->assertSee('Join video call')->assertDontSee('Pay to confirm');
    }

    public function test_payment_taken_at_the_desk_also_confirms_the_call(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->bookOnline($patient, $doctor, $location);
        $invoice = $this->invoiceFor($appointment, $organization, $patient);

        app(BillingService::class)->recordPayment($invoice, 50000, 'cash');

        $this->assertFalse($appointment->refresh()->isAwaitingPayment());
    }

    public function test_a_part_payment_does_not_release_the_link(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->bookOnline($patient, $doctor, $location);

        app(BillingService::class)->recordPayment($this->invoiceFor($appointment, $organization, $patient), 10000, 'cash');

        $this->assertTrue($appointment->refresh()->isAwaitingPayment());
    }

    public function test_payment_is_not_demanded_without_a_gateway_or_a_fee_or_from_staff_bookings(): void
    {
        [, $location, $doctor, $patient] = $this->clinic(hasGateway: false);
        $this->assertFalse($this->bookOnline($patient, $doctor, $location)->isAwaitingPayment(), 'No gateway.');

        [, $location, $doctor, $patient] = $this->clinic(feePaise: 0);
        $this->assertFalse($this->bookOnline($patient, $doctor, $location)->isAwaitingPayment(), 'No fee.');

        [, $location, $doctor, $patient] = $this->clinic();
        $this->assertFalse($this->bookOnline($patient, $doctor, $location, 'staff')->isAwaitingPayment(), 'Booked by staff.');
    }

    public function test_an_unpaid_call_is_released_and_only_after_its_deadline(): void
    {
        [, $location, $doctor, $patient] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        $appointment = $this->bookOnline($patient, $doctor, $location);
        Queue::fake();

        $this->assertSame(0, app(VideoPayments::class)->releaseUnpaid());

        $this->travel(31)->minutes();

        $this->assertSame(1, app(VideoPayments::class)->releaseUnpaid());
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNull($appointment->slot_key);
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'video_payment_missed' && ! array_key_exists('link', $job->variables));
    }

    public function test_the_hold_never_outlasts_the_appointment(): void
    {
        [, $location, $doctor, $patient] = $this->clinic();
        $this->travelTo($this->monday->subMinutes(10));

        $this->assertTrue($this->bookOnline($patient, $doctor, $location)->pay_by->equalTo($this->monday));
    }

    public function test_a_late_payment_gets_the_time_back_if_it_is_still_free(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->bookOnline($patient, $doctor, $location);
        $invoice = $this->invoiceFor($appointment, $organization, $patient);
        $this->travel(31)->minutes();
        app(VideoPayments::class)->releaseUnpaid();

        $this->payOnline($invoice);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertTrue($appointment->canJoinVideo());
    }

    public function test_a_late_payment_for_a_taken_time_tells_the_owner(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->bookOnline($patient, $doctor, $location);
        $invoice = $this->invoiceFor($appointment, $organization, $patient);
        $this->travel(31)->minutes();
        app(VideoPayments::class)->releaseUnpaid();
        $this->bookOnline(Patient::factory()->create(['organization_id' => $organization->id]), $doctor, $location, 'staff');
        Queue::fake();

        $this->payOnline($invoice);

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'video_refund_needed');
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'appointments.video_paid_after_release')->exists());
    }

    public function test_a_reminder_does_not_leak_the_link_before_payment(): void
    {
        [, $location, $doctor, $patient] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        $this->bookOnline($patient, $doctor, $location);
        Queue::fake();
        $this->travelTo($this->monday->subHours(1)->setTime(9, 0));

        app(ReminderScheduler::class)->run();

        Queue::assertNotPushed(SendMessage::class, fn (SendMessage $job) => in_array($job->event, ['video_consult', 'video_consult_now'], true));
    }

    public function test_staff_buttons_do_not_leak_the_link_before_payment(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->bookOnline($patient, $doctor, $location);

        $this->actingAs($doctor, 'web')->get(route('app.appointments.index', ['date' => $this->monday->toDateString()]))->assertOk()->assertSee('Awaiting payment')->assertDontSee('Join video')->assertDontSee(rawurlencode((string) $appointment->video_link), false);
    }

    public function test_staff_must_record_the_patients_agreement_to_a_video_call(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $owner = $this->addMember($organization, ['owner'], location: $location);
        $booking = ['patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'starts_at' => $this->monday->format('Y-m-d H:i'), 'mode' => 'online'];
        $this->actingAs($owner, 'web');

        $this->post(route('app.appointments.store'), $booking)->assertSessionHasErrors(['video_consent' => 'Please confirm the patient has agreed to a video call.']);
        $this->assertSame(0, Appointment::withoutTenancy()->count());

        $this->post(route('app.appointments.store'), $booking + ['video_consent' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'appointments.video_consent_recorded')->exists());
    }

    public function test_the_patient_is_told_when_staff_cancel_or_move_an_appointment(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $owner = $this->addMember($organization, ['owner'], location: $location);
        $this->clinicsHaveOwnEmail();
        $appointment = app(AppointmentService::class)->bookSlot($patient, $doctor->id, $location->id, $this->monday, VisitType::New);
        Queue::fake();
        $this->actingAs($owner, 'web');

        $this->put(route('app.appointments.update', $appointment), ['starts_at' => $this->monday->addMinutes(15)->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'appointment_moved');

        $this->post(route('app.appointments.cancel', $appointment), ['reason' => 'Doctor unwell'])->assertSessionHasNoErrors();
        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'appointment_cancelled');
    }

    public function test_video_is_on_by_default_and_visible_on_the_clinic_website(): void
    {
        [$organization, $location, $doctor] = $this->clinic();

        $this->assertTrue(DoctorProfile::query()->where('user_id', $doctor->id)->firstOrFail()->refresh()->offers_video);
        $this->get(route('clinic.home', $organization->slug))->assertOk()->assertSee('Book a video call')->assertSee('Video calls available');
        $this->get(route('booking.show', [$organization->slug, 'mode' => 'online', 'location' => $location->id, 'doctor' => $doctor->id, 'date' => $this->monday->toDateString()]))->assertOk()->assertSee('How would you like to meet?')->assertSee('value="online" form="slot-form" checked', false);
    }

    public function test_a_doctor_who_turned_video_off_is_not_offered_for_it(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $other = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Video']), $location);
        DoctorProfile::query()->create(['user_id' => $other->id]);
        DoctorProfile::query()->where('user_id', $doctor->id)->update(['offers_video' => false]);
        $doctor->forceFill(['name' => 'Dr Clinic Only'])->save();

        $this->get(route('booking.show', [$organization->slug, 'mode' => 'online', 'location' => $location->id]))->assertOk()->assertSee('Dr Video')->assertDontSee('Dr Clinic Only');
    }

    /**
     * @return array{Visit, User}
     */
    private function alliedVisit(string $specialty): array
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        DoctorProfile::query()->where('user_id', $doctor->id)->update(['specialty' => $specialty]);

        return [Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]), $doctor];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function plan(array $items): array
    {
        return ['items_json' => json_encode($items), 'instructions_language' => 'en'];
    }

    public function test_a_physiotherapist_searches_only_their_own_field(): void
    {
        [, $doctor] = $this->alliedVisit('Physiotherapist');
        Medicine::factory()->create(['generic_name' => 'Diclofenac Gel Plus', 'brand_name' => null, 'specialty' => 'Physiotherapist', 'system' => 'Allied Health']);
        Medicine::factory()->create(['generic_name' => 'Diclofenac', 'brand_name' => null, 'specialty' => 'General Physician', 'system' => 'Allopathy']);

        $rows = $this->actingAs($doctor, 'web')->getJson(route('app.medicines.search', ['q' => 'diclo']))->assertOk()->json();

        $this->assertSame(['Diclofenac Gel Plus'], array_column($rows, 'generic'));
    }

    public function test_a_dietitian_can_also_add_the_listed_supplements(): void
    {
        [, $doctor] = $this->alliedVisit('Dietitian');
        Medicine::factory()->create(['generic_name' => 'Vitamin D3', 'brand_name' => null, 'specialty' => 'General Physician', 'system' => 'Allopathy']);

        $rows = $this->actingAs($doctor, 'web')->getJson(route('app.medicines.search', ['q' => 'vitamin']))->assertOk()->json();

        $this->assertSame(['Vitamin D3'], array_column($rows, 'generic'));
    }

    public function test_medicines_outside_the_profession_are_refused_chosen_or_typed(): void
    {
        [$visit, $doctor] = $this->alliedVisit('Physiotherapist');
        $antibiotic = Medicine::factory()->create(['generic_name' => 'Amoxicillin', 'brand_name' => null, 'specialty' => 'General Physician', 'system' => 'Allopathy']);
        $this->actingAs($doctor, 'web');

        $this->put(route('app.prescriptions.update', $visit), $this->plan([['medicine_id' => $antibiotic->id, 'medicine_name' => 'Anything']]))->assertSessionHasErrors('items_json');
        $this->put(route('app.prescriptions.update', $visit), $this->plan([['medicine_name' => 'amoxicillin 500 mg']]))->assertSessionHasErrors('items_json');

        $this->put(route('app.prescriptions.update', $visit), $this->plan([['medicine_name' => 'Quadriceps strengthening, 3 sets of 10']]))->assertSessionHasNoErrors();
    }

    public function test_a_doctor_is_not_limited_and_an_allied_plan_is_titled_for_what_it_is(): void
    {
        [$doctorVisit, $doctor] = $this->alliedVisit('General Physician');
        Medicine::factory()->create(['generic_name' => 'Amoxicillin', 'brand_name' => null, 'specialty' => 'General Physician', 'system' => 'Allopathy']);
        $this->actingAs($doctor, 'web')->put(route('app.prescriptions.update', $doctorVisit), $this->plan([['medicine_name' => 'Amoxicillin 500 mg']]))->assertSessionHasNoErrors();
        $this->get(route('app.prescriptions.show', Prescription::withoutTenancy()->where('visit_id', $doctorVisit->id)->value('id')))->assertOk()->assertSee('Medicines')->assertDontSee('Treatment plan');

        [$visit, $physio] = $this->alliedVisit('Physiotherapist');
        $this->actingAs($physio, 'web')->put(route('app.prescriptions.update', $visit), $this->plan([['medicine_name' => 'Hamstring stretches']]))->assertSessionHasNoErrors();
        $this->get(route('app.prescriptions.show', Prescription::withoutTenancy()->where('visit_id', $visit->id)->value('id')))->assertOk()->assertSee('Treatment plan')->assertSee('Physiotherapist')->assertDontSee('Not provided');
    }

    public function test_a_video_consultation_says_so_on_the_prescription(): void
    {
        [$organization, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->bookOnline($patient, $doctor, $location, 'staff');
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'appointment_id' => $appointment->id, 'visited_on' => today()]);

        $this->actingAs($doctor, 'web')->put(route('app.prescriptions.update', $visit), $this->plan([['medicine_name' => 'Paracetamol']]))->assertSessionHasNoErrors();

        $this->get(route('app.prescriptions.show', Prescription::withoutTenancy()->where('visit_id', $visit->id)->value('id')))->assertOk()->assertSee('Video consultation');
    }

    /**
     * @return array<string, mixed>
     */
    private function physioSignup(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'solo_practice', 'name' => 'Asha Nair', 'email' => 'asha@example.test', 'mobile' => '9812345678',
            'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1', 'address_line' => '12 Park Road', 'city' => 'Kochi',
            'qualification' => 'BPT', 'specialty' => 'Physiotherapist',
        ];
    }

    public function test_an_allied_professional_can_sign_up_with_a_certificate_instead_of_a_number(): void
    {
        Storage::fake(config('filesystems.default'));

        $this->from(route('register'))->post(route('register.store'), $this->physioSignup())->assertSessionHasErrors(['registration_number' => 'Please enter your registration number, or attach your degree or membership certificate.']);

        $this->post(route('register.store'), $this->physioSignup(['certificate' => UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf')]))->assertSessionHasNoErrors();

        $organization = Organization::query()->firstOrFail();
        $this->assertSame(1, OrganizationDocument::withoutTenancy()->where('organization_id', $organization->id)->where('type', OrganizationDocument::PROFESSIONAL_CERTIFICATE)->count());
        $this->assertTrue(app(OrganizationVerification::class)->isComplete($organization));
    }

    public function test_approval_verifies_an_allied_owner_who_sent_a_certificate(): void
    {
        Storage::fake(config('filesystems.default'));
        $this->post(route('register.store'), $this->physioSignup(['certificate' => UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf')]));
        $organization = Organization::query()->firstOrFail();
        $organization->forceFill(['status' => OrganizationStatus::PendingVerification, 'approved_at' => null])->save();

        app(OrganizationVerification::class)->approve($organization, $this->superAdmin());

        $this->assertSame('verified', DoctorProfile::query()->firstOrFail()->verification_status);
    }

    public function test_a_certificate_does_not_replace_a_doctors_registration_number(): void
    {
        $this->from(route('register'))->post(route('register.store'), $this->physioSignup(['specialty' => 'Cardiologist', 'certificate' => UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf')]))->assertSessionHasErrors(['registration_number' => 'Please enter your registration number.']);
    }

    public function test_an_invited_allied_professional_sends_a_certificate_and_our_team_sees_it(): void
    {
        Storage::fake(config('filesystems.default'));
        [, $location, $doctor] = $this->clinic();
        DoctorProfile::query()->where('user_id', $doctor->id)->update(['specialty' => 'Dietitian']);
        $this->assertTrue(app(DoctorVerification::class)->waiting()->isEmpty());

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.certificate'), ['certificate' => UploadedFile::fake()->create('member.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();

        $this->assertSame($doctor->id, (int) app(DoctorVerification::class)->waiting()->first()['profile']->user_id);
        auth('web')->logout();
        $this->actingAs($this->superAdmin(), 'platform')->get(route('admin.approvals.index'))->assertOk()->assertSee('View degree or membership certificate');
    }

    public function test_only_allied_professionals_use_the_certificate_route(): void
    {
        [, , $doctor] = $this->clinic();
        DoctorProfile::query()->where('user_id', $doctor->id)->update(['specialty' => 'Cardiologist']);

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.certificate'), ['certificate' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertForbidden();
    }

    public function test_a_script_disguised_as_a_certificate_is_refused(): void
    {
        [, , $doctor] = $this->clinic();
        DoctorProfile::query()->where('user_id', $doctor->id)->update(['specialty' => 'Dietitian']);

        $path = tempnam(sys_get_temp_dir(), 'cert');
        file_put_contents($path, '<html><script>alert(1)</script></html>');

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.certificate'), ['certificate' => new UploadedFile($path, 'x.pdf', null, null, true)])->assertSessionHasErrors('certificate');

        unlink($path);
    }
}
