<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\DataRequest;
use App\Models\DoctorAvailability;
use App\Models\DoctorFee;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitAttachment;
use App\Services\Billing\BillingService;
use App\Services\Portal\AccountEraser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientPortalTest extends TestCase
{
    /**
     * @return array{Organization, Location, User, Patient, Visit}
     */
    private function careAt(PatientAccount $account, string $name = 'Ravi'): array
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => $name, 'email' => $account->email, 'patient_account_id' => $account->id]);
        $visit = Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'diagnosis' => 'Secret illness']);

        return [$organization, $location, $doctor, $patient, $visit];
    }

    private function prescription(Visit $visit): Prescription
    {
        return Prescription::withoutTenancy()->create([
            'organization_id' => $visit->organization_id, 'visit_id' => $visit->id, 'patient_id' => $visit->patient_id, 'location_id' => $visit->location_id,
            'doctor_user_id' => $visit->doctor_user_id, 'doctor_name' => 'Dr Portal', 'issued_at' => now(),
        ]);
    }

    public function test_otp_login_links_this_emails_records_from_every_organization(): void
    {
        [$a] = $this->organizationWithOwner();
        [$b] = $this->organizationWithOwner();
        $mine = Patient::factory()->create(['organization_id' => $a->id, 'email' => 'ravi@example.test', 'name' => 'Ravi']);
        $mineElsewhere = Patient::factory()->create(['organization_id' => $b->id, 'email' => 'ravi@example.test', 'name' => 'Ravi K']);
        $someoneElse = Patient::factory()->create(['organization_id' => $a->id, 'email' => 'other@example.test']);

        $this->post(route('patient.login.send'), ['email' => 'Ravi@Example.test'])->assertRedirect(route('patient.login.verify'));
        $this->from(route('patient.login.verify'))->post(route('patient.login.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest('patient');

        $this->post(route('patient.login.confirm'), ['code' => $this->lastEmailedCode()])->assertRedirect(route('patient.home'));

        $account = PatientAccount::query()->firstOrFail();
        $this->assertAuthenticatedAs($account, 'patient');
        $this->assertSame($account->id, (int) Patient::withoutTenancy()->find($mine->id)->patient_account_id);
        $this->assertSame($account->id, (int) Patient::withoutTenancy()->find($mineElsewhere->id)->patient_account_id);
        $this->assertNull(Patient::withoutTenancy()->find($someoneElse->id)->patient_account_id);
        $this->assertSame(3, Patient::withoutTenancy()->count(), 'Records are linked, never merged.');
    }

    public function test_the_portal_requires_login_and_staff_sessions_do_not_count(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->get(route('patient.home'))->assertRedirect(route('patient.login'));
        $this->actingAs($owner, 'web')->get(route('patient.records'))->assertRedirect(route('patient.login'));
    }

    public function test_a_patient_sees_their_records_from_all_organizations(): void
    {
        $account = PatientAccount::factory()->create();
        [$a, , , , $visitA] = $this->careAt($account);
        [$b, , , , $visitB] = $this->careAt($account);
        $this->prescription($visitA);
        $this->prescription($visitB);

        $this->actingAs($account, 'patient')->get(route('patient.records'))->assertOk()->assertSee($a->name)->assertSee($b->name);
    }

    public function test_a_patient_cannot_reach_anyone_elses_records(): void
    {
        Storage::fake('local');
        $me = PatientAccount::factory()->create();
        $other = PatientAccount::factory()->create();
        [$organization, $location, $doctor, $patient, $visit] = $this->careAt($other);
        $this->careAt($me);
        $prescription = $this->prescription($visit);
        $invoice = app(BillingService::class)->createInvoice($organization, $patient, [['description' => 'x', 'quantity' => 1, 'unit_paise' => 100, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id]);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);
        $report = VisitAttachment::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'disk' => 'local', 'path' => 'x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1]);

        $this->actingAs($me, 'patient');

        $this->get(route('patient.prescriptions.pdf', $prescription->id))->assertNotFound();
        $this->get(route('patient.invoices.pdf', $invoice->id))->assertNotFound();
        $this->get(route('patient.appointments.show', $appointment->id))->assertNotFound();
        $this->post(route('patient.appointments.cancel', $appointment->id), ['reason' => 'x'])->assertNotFound();
        $this->get(route('patient.reports.show', $report->id))->assertNotFound();
        $this->get(route('patient.records'))->assertOk()->assertDontSee('x.pdf');
    }

    public function test_pdf_downloads_work_without_a_tenant_in_the_session(): void
    {
        $account = PatientAccount::factory()->create();
        [, , , , $visit] = $this->careAt($account);
        $prescription = $this->prescription($visit);

        $response = $this->actingAs($account, 'patient')->get(route('patient.prescriptions.pdf', $prescription->id))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(1, AuditLog::withoutTenancy()->where('action', 'prescriptions.downloaded_by_patient')->where('actor_type', 'patient')->count());
    }

    public function test_only_reports_marked_visible_can_be_downloaded(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('r.pdf', 'pdf');
        $account = PatientAccount::factory()->create();
        [$organization, , , , $visit] = $this->careAt($account);
        $visible = VisitAttachment::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'disk' => 'local', 'path' => 'r.pdf', 'original_name' => 'shown.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3]);
        $hidden = VisitAttachment::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'disk' => 'local', 'path' => 'r.pdf', 'original_name' => 'internal.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3, 'patient_visible' => false]);

        $this->actingAs($account, 'patient');

        $this->get(route('patient.records'))->assertSee('shown.pdf')->assertDontSee('internal.pdf');
        $this->get(route('patient.reports.show', $visible->id))->assertOk();
        $this->get(route('patient.reports.show', $hidden->id))->assertNotFound();
    }

    public function test_a_patient_can_cancel_and_move_a_future_appointment_only(): void
    {
        $account = PatientAccount::factory()->create();
        [$organization, $location, $doctor, $patient] = $this->careAt($account);
        $monday = CarbonImmutable::parse('next monday 10:00');
        DoctorAvailability::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'weekday' => $monday->dayOfWeek, 'start_time' => '10:00', 'end_time' => '11:00', 'slot_minutes' => 15]);
        $appointment = Appointment::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
            'status' => AppointmentStatus::Booked, 'scheduled_on' => $monday->toDateString(), 'starts_at' => $monday, 'ends_at' => $monday->addMinutes(15), 'slot_key' => Appointment::slotKey($monday),
        ]);
        $past = Appointment::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
            'status' => AppointmentStatus::Booked, 'scheduled_on' => now()->subDay()->toDateString(), 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay(),
        ]);
        $this->actingAs($account, 'patient');

        $this->get(route('patient.appointments.show', $appointment->id))->assertOk()->assertSee('10:15 AM');
        $this->post(route('patient.appointments.reschedule', $appointment->id), ['starts_at' => $monday->addMinutes(15)->format('Y-m-d H:i')])->assertRedirect();
        $this->assertSame('10:15', Appointment::withoutTenancy()->find($appointment->id)->starts_at->format('H:i'));

        $this->post(route('patient.appointments.cancel', $past->id), ['reason' => 'x'])->assertStatus(422);

        $this->post(route('patient.appointments.cancel', $appointment->id), ['reason' => 'Better'])->assertRedirect(route('patient.appointments'));
        $this->assertSame(AppointmentStatus::Cancelled, Appointment::withoutTenancy()->find($appointment->id)->status);
    }

    public function test_online_payment_makes_a_bill_and_a_link_without_exposing_keys(): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_1', 'short_url' => 'https://rzp.io/i/x'])]);
        $account = PatientAccount::factory()->create();
        [$organization, $location, $doctor, $patient] = $this->careAt($account);
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'razorpay', 'is_enabled' => true, 'config' => ['key_id' => 'rzp_visible_key', 'key_secret' => 'secret_value', 'webhook_secret' => 'w']]);
        DoctorFee::withoutTenancy()->create(['organization_id' => $organization->id, 'doctor_user_id' => null, 'visit_type' => 'new', 'amount_paise' => 50000]);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::Booked]);

        $response = $this->actingAs($account, 'patient')->post(route('patient.appointments.pay', $appointment->id))->assertOk();

        $response->assertSee('https://rzp.io/i/x')->assertSee('₹500.00')->assertDontSee('rzp_visible_key')->assertDontSee('secret_value');
        $this->assertSame(50000, Invoice::withoutTenancy()->firstOrFail()->total_paise);

        // Pressing Pay again reuses the same bill.
        $this->post(route('patient.appointments.pay', $appointment->id))->assertOk();
        $this->assertSame(1, Invoice::withoutTenancy()->count());
    }

    public function test_pay_is_404_when_the_clinic_has_no_online_payments(): void
    {
        $account = PatientAccount::factory()->create();
        [$organization, $location, $doctor, $patient] = $this->careAt($account);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);

        $this->actingAs($account, 'patient')->post(route('patient.appointments.pay', $appointment->id))->assertNotFound();
    }

    public function test_cross_organization_history_needs_the_patients_otp_and_every_look_is_logged(): void
    {
        $account = PatientAccount::factory()->create();
        [$home] = $this->careAt($account);
        [$visited, $visitedLocation, $doctor, $patient] = $this->careAt($account);
        $this->actingAs($doctor, 'web');

        $this->get(route('app.patients.history', $patient))->assertOk()->assertDontSee('Secret illness')->assertSee('Email the patient a code');

        $this->post(route('app.patients.history.request', $patient));
        $this->assertSame(1, $this->emailedCodeCount());

        $this->from(route('app.patients.history', $patient))->post(route('app.patients.history.confirm', $patient), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertSame(0, Consent::query()->count());

        $this->post(route('app.patients.history.confirm', $patient), ['code' => $this->lastEmailedCode()]);

        $this->get(route('app.patients.history', $patient))->assertOk()->assertSee('Secret illness')->assertSee($home->name);

        $this->assertSame(1, AuditLog::withoutTenancy()->where('action', 'patients.cross_org_viewed')->where('organization_id', $visited->id)->count());
        $this->assertSame(1, AuditLog::withoutTenancy()->where('action', 'patients.record_shared')->where('organization_id', $home->id)->count());
        $this->assertNotNull($visitedLocation);
    }

    public function test_a_patient_without_an_email_cannot_be_asked_for_consent(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($doctor, 'web')->post(route('app.patients.history.request', $patient))->assertSessionHas('error');

        $this->assertSame(0, $this->emailedCodeCount());
    }

    public function test_consent_binds_one_doctor_and_revoking_ends_it_at_once(): void
    {
        $account = PatientAccount::factory()->create();
        $this->careAt($account);
        [$organization, $location, $doctor, $patient] = $this->careAt($account);
        $colleague = $this->addMember($organization, ['doctor'], location: $location);
        $consent = Consent::query()->create(['patient_account_id' => $account->id, 'purpose' => Consent::CROSS_ORG_HISTORY, 'grantee_organization_id' => $organization->id, 'grantee_doctor_user_id' => $doctor->id, 'granted_via' => 'otp', 'granted_at' => now()]);

        $this->actingAs($colleague, 'web')->get(route('app.patients.history', $patient))->assertOk()->assertDontSee('Secret illness');
        $this->actingAs($doctor, 'web')->get(route('app.patients.history', $patient))->assertOk()->assertSee('Secret illness');

        $this->actingAs($account, 'patient')->get(route('patient.consents'))->assertOk()->assertSee($organization->name);
        $this->post(route('patient.consents.revoke', $consent->id))->assertRedirect();

        $this->actingAs($doctor, 'web')->get(route('app.patients.history', $patient))->assertOk()->assertDontSee('Secret illness');
    }

    public function test_consent_expires(): void
    {
        $account = PatientAccount::factory()->create();
        $this->careAt($account);
        [$organization, , $doctor, $patient] = $this->careAt($account);
        Consent::query()->create(['patient_account_id' => $account->id, 'purpose' => Consent::CROSS_ORG_HISTORY, 'grantee_organization_id' => $organization->id, 'grantee_doctor_user_id' => $doctor->id, 'granted_via' => 'otp', 'granted_at' => now(), 'expires_at' => now()->addDay()]);

        $this->travel(2)->days();

        $this->actingAs($doctor, 'web')->get(route('app.patients.history', $patient))->assertOk()->assertDontSee('Secret illness');
    }

    public function test_a_patient_cannot_revoke_another_patients_consent(): void
    {
        $owner = PatientAccount::factory()->create();
        $me = PatientAccount::factory()->create();
        [$organization, , $doctor] = $this->careAt($owner);
        $consent = Consent::query()->create(['patient_account_id' => $owner->id, 'purpose' => Consent::CROSS_ORG_HISTORY, 'grantee_organization_id' => $organization->id, 'grantee_doctor_user_id' => $doctor->id, 'granted_via' => 'otp', 'granted_at' => now()]);

        $this->actingAs($me, 'patient')->post(route('patient.consents.revoke', $consent->id))->assertNotFound();

        $this->assertNull(Consent::query()->find($consent->id)->revoked_at);
    }

    public function test_download_my_data_contains_only_my_data(): void
    {
        $me = PatientAccount::factory()->create();
        $other = PatientAccount::factory()->create();
        $this->careAt($me, 'My Own Name');
        $this->careAt($other, 'Stranger Name');

        $response = $this->actingAs($me, 'patient')->get(route('patient.privacy.download'))->assertOk();

        $response->assertSee('My Own Name')->assertSee('Secret illness')->assertDontSee('Stranger Name');
        $response->assertHeader('Content-Disposition', 'attachment; filename="my-health-data.json"');
    }

    public function test_requests_reach_the_admin_and_deletion_keeps_clinical_records(): void
    {
        $account = PatientAccount::factory()->create();
        [, , , $patient, $visit] = $this->careAt($account);
        $this->actingAs($account, 'patient');

        $this->from(route('patient.privacy'))->post(route('patient.privacy.requests'), ['type' => 'correction'])->assertSessionHasErrors('details');
        $this->post(route('patient.privacy.requests'), ['type' => 'deletion'])->assertSessionHas('success');

        $request = DataRequest::query()->firstOrFail();

        $this->actingAs($this->superAdmin(), 'platform');
        $this->get(route('admin.data-requests.index'))->assertOk();
        $this->post(route('admin.data-requests.resolve', $request), ['status' => 'completed', 'resolution_notes' => 'Done'])->assertSessionHas('success');

        $this->assertSoftDeleted('patient_accounts', ['id' => $account->id]);
        $this->assertNotSame($account->email, PatientAccount::withTrashed()->find($account->id)->email);
        $this->assertNull(Patient::withoutTenancy()->find($patient->id)->patient_account_id);
        $this->assertNotNull(Visit::withoutTenancy()->find($visit->id), 'Medical records stay with the clinic.');
    }

    public function test_an_erased_email_can_start_again_without_the_old_links(): void
    {
        $account = PatientAccount::factory()->create(['email' => 'back@example.test']);
        app(AccountEraser::class)->erase($account);

        $fresh = PatientAccount::query()->firstOrCreate(['email' => 'back@example.test']);

        $this->assertNotSame($account->id, $fresh->id);
    }

    public function test_the_language_setting_sticks_to_the_account(): void
    {
        $account = PatientAccount::factory()->create();
        $this->actingAs($account, 'patient');

        $this->post(route('patient.language'), ['locale' => 'bn'])->assertRedirect();
        $this->get(route('patient.home'))->assertOk()->assertSee('আসন্ন অ্যাপয়েন্টমেন্ট');

        $this->from(route('patient.home'))->post(route('patient.language'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_hindi_and_bengali_translate_the_same_strings(): void
    {
        $hindi = json_decode((string) file_get_contents(lang_path('hi.json')), true);
        $bengali = json_decode((string) file_get_contents(lang_path('bn.json')), true);

        $this->assertSame(array_keys($hindi), array_keys($bengali));
    }
}
