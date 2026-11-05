<?php

namespace Tests\Feature;

use App\Jobs\SendMessage;
use App\Jobs\SendPrescriptionEmail;
use App\Models\MessageLog;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\Messaging\Notifier;
use App\Services\OrganizationMail;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PatientEmailRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No SMS gateway, so email is the only channel in play.
        config(['cureplus.sms.driver' => 'http', 'cureplus.sms.http.url' => null]);
    }

    public function test_without_their_own_email_patients_are_not_emailed_from_the_platform(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization] = $this->organizationWithOwner();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test']);

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'Dr']);

        Bus::assertNotDispatched(SendMessage::class);
        $this->assertSame('no_clinic_email', MessageLog::withoutTenancy()->firstOrFail()->status, 'Recorded, so the owner can see why.');
    }

    public function test_with_their_own_email_patients_are_emailed(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization] = $this->organizationWithOwner();
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'smtp', 'is_enabled' => true, 'config' => ['host' => 'smtp.example.test', 'username' => 'clinic@example.test', 'password' => 'x']]);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test']);

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'Dr']);

        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->channel === 'email' && $job->organizationId === $organization->id);
    }

    public function test_one_clinics_email_is_never_used_for_another(): void
    {
        [$first] = $this->organizationWithOwner();
        [$second] = $this->organizationWithOwner();
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $first->id, 'kind' => 'smtp', 'is_enabled' => true, 'config' => ['host' => 'smtp.example.test']]);
        $mail = app(OrganizationMail::class);

        $this->assertTrue($mail->hasOwnEmail($first->id));
        $this->assertFalse($mail->hasOwnEmail($second->id));
        $this->assertNull($mail->mailerFor($second->id));
    }

    public function test_a_switched_off_email_counts_as_not_connected(): void
    {
        [$organization] = $this->organizationWithOwner();
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'smtp', 'is_enabled' => false, 'config' => ['host' => 'smtp.example.test']]);

        $this->assertFalse(app(OrganizationMail::class)->hasOwnEmail($organization->id));
    }

    public function test_mail_goes_out_as_the_clinic_never_as_the_platform(): void
    {
        config(['mail.from.address' => 'platform@example.test']);

        $mailer = app(OrganizationMail::class)->build(['host' => 'smtp.example.test', 'username' => 'clinic@example.test', 'password' => 'x']);
        $from = (new \ReflectionProperty($mailer, 'from'))->getValue($mailer);

        $this->assertSame('clinic@example.test', $from['address']);
    }

    public function test_a_message_queued_before_disconnecting_is_not_sent_by_the_platform(): void
    {
        [$organization] = $this->organizationWithOwner();
        $log = MessageLog::withoutTenancy()->create(['organization_id' => $organization->id, 'channel' => 'email', 'event' => 'token_called', 'recipient_masked' => 'x', 'status' => 'queued']);

        SendMessage::dispatchSync($log->id, 'email', 'token_called', 'p@example.test', ['name' => 'P', 'token' => 1, 'doctor' => 'Dr'], $organization->id);

        $this->assertSame('no_clinic_email', $log->fresh()->status);
        $this->assertCount(0, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_sign_in_codes_still_come_from_the_platform_without_clinic_email(): void
    {
        $this->post(route('patient.login.send'), ['email' => 'p@example.test'])->assertRedirect();

        $this->assertNotEmpty($this->lastEmailedCode());
    }

    public function test_staff_are_told_plainly_when_nothing_can_be_sent(): void
    {
        Bus::fake([SendPrescriptionEmail::class, SendMessage::class]);
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test']);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr', 'issued_at' => now()]);
        $this->actingAs($owner, 'web');

        $this->post(route('app.prescriptions.email', $prescription))->assertSessionHas('error');
        $this->post(route('app.prescriptions.share', $prescription))->assertSessionHas('error');
        Bus::assertNotDispatched(SendPrescriptionEmail::class);
    }

    public function test_the_integrations_page_shows_what_was_missed(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test']);
        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'Dr']);

        $this->actingAs($owner, 'web')->get(route('app.integrations.index'))->assertOk()
            ->assertSee('Not connected')
            ->assertSee('1 email to your patients could not be sent');
        $this->get(route('app.dashboard'))->assertSee('Connect your email');
    }

    public function test_a_test_email_needs_saved_details(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.integrations.smtp.test'), ['to' => 'x@example.test'])->assertSessionHas('error', 'Save your email details first.');
    }
}
