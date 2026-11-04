<?php

namespace Tests\Feature;

use App\Actions\RegisterPatient;
use App\Enums\OrganizationStatus;
use App\Jobs\SendMessage;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DoctorProfile;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\PlatformAdmin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\Messaging\Notifier;
use App\Services\Payments\GatewayFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationApprovalTest extends TestCase
{
    /**
     * @return array{Organization, User, Location}
     */
    private function pendingClinic(): array
    {
        $organization = Organization::factory()->pending()->create();
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $owner = $this->addMember($organization, ['owner'], location: $location);

        return [$organization, $owner, $location];
    }

    private function withProof(Organization $organization): void
    {
        OrganizationDocument::factory()->create(['organization_id' => $organization->id]);
    }

    /**
     * @return array<string, string>
     */
    private function signup(): array
    {
        return ['type' => 'clinic', 'organization_name' => 'New Clinic', 'name' => 'Asha Sen', 'email' => 'asha@example.test', 'mobile' => '9812345678', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'];
    }

    public function test_a_signup_waits_for_verification_and_tells_the_super_admin(): void
    {
        Bus::fake([SendMessage::class]);
        PlatformAdmin::factory()->create(['email' => 'boss@example.test', 'role' => 'super_admin']);
        PlatformAdmin::factory()->create(['email' => 'sales@example.test', 'role' => 'sales']);

        $this->post('/register', $this->signup())->assertRedirect();

        $this->assertSame(OrganizationStatus::PendingVerification, Organization::query()->firstOrFail()->status);
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_signup' && $job->to === 'boss@example.test');
        Bus::assertNotDispatched(SendMessage::class, fn (SendMessage $job) => $job->to === 'sales@example.test');
    }

    public function test_a_pending_practice_can_sign_in_set_up_and_sees_the_banner(): void
    {
        [, $owner, $location] = $this->pendingClinic();
        $this->actingAs($owner, 'web');

        $this->get(route('app.dashboard'))->assertOk()->assertSee('Your account is under verification (usually within 24 hours)');
        $this->get(route('app.staff.index'))->assertOk();
        $this->get(route('app.locations.edit', $location))->assertOk();
        $this->get(route('app.billing-settings.index'))->assertOk();
        $this->get(route('app.verification.index'))->assertOk();
    }

    public function test_an_approved_practice_sees_no_banner(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk()->assertDontSee('under verification');
    }

    public function test_real_patients_are_blocked_by_route_policy_and_action(): void
    {
        [$organization, $owner] = $this->pendingClinic();
        $this->actingAs($owner, 'web');

        $this->post(route('app.patients.store'), ['name' => 'Real Person', 'mobile' => '9812345678'])->assertSessionHas('error');
        $this->get(route('app.patients.create'))->assertRedirect(route('app.verification.index'));
        $this->assertSame(0, Patient::withoutTenancy()->where('organization_id', $organization->id)->count());

        $this->get(route('app.dashboard'));
        $this->assertFalse($owner->can('create', Patient::class), 'The policy must refuse even if a route forgot the middleware.');

        $this->expectException(AuthorizationException::class);
        app(RegisterPatient::class)($organization->id, ['name' => 'Real Person', 'mobile' => '+919812345678']);
    }

    public function test_public_booking_and_the_practice_brand_are_hidden_until_approved(): void
    {
        [$organization] = $this->pendingClinic();

        $this->get('/'.$organization->slug)->assertNotFound();

        $this->withProof($organization);
        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization))->assertSessionHas('success');

        $this->get('/'.$organization->slug)->assertOk()->assertSee($organization->name);
    }

    public function test_prescription_printing_is_blocked_until_approved(): void
    {
        [$organization, $owner, $location] = $this->pendingClinic();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'is_demo' => true]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr Test', 'issued_at' => now()]);

        $this->actingAs($owner, 'web');

        foreach (['app.prescriptions.show', 'app.prescriptions.pdf'] as $route) {
            $this->get(route($route, $prescription))->assertRedirect(route('app.verification.index'));
        }

        foreach (['app.prescriptions.email', 'app.prescriptions.share'] as $route) {
            $this->post(route($route, $prescription))->assertSessionHas('error');
        }
    }

    public function test_online_payments_and_paid_messages_are_off_until_approved(): void
    {
        config(['cureplus.sms.driver' => 'log']);
        Bus::fake([SendMessage::class]);
        [$organization] = $this->pendingClinic();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => null]);
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'razorpay', 'is_enabled' => true, 'config' => ['key_id' => 'k', 'key_secret' => 's', 'webhook_secret' => 'w']]);

        $this->assertNull(app(GatewayFactory::class)->forOrganization($organization->id));

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'Dr']);
        Bus::assertNotDispatched(SendMessage::class);
    }

    public function test_sample_patients_work_and_approval_removes_them_completely(): void
    {
        [$organization, $owner, $location] = $this->pendingClinic();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $this->withProof($organization);

        $this->actingAs($owner, 'web')->post(route('app.verification.sample-data'))->assertRedirect(route('app.patients.index'));
        $this->post(route('app.verification.sample-data'));
        $this->get(route('app.patients.index'))->assertSee('Sample Patient One')->assertSee('Sample');
        $sample = Patient::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(3, Patient::withoutTenancy()->where('organization_id', $organization->id)->count());

        $appointment = Appointment::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $sample->id, 'doctor_user_id' => $doctor->id, 'scheduled_on' => today(), 'token_number' => 1, 'status' => 'booked', 'visit_type' => 'new', 'source' => 'desk']);
        Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $sample->id, 'doctor_user_id' => $doctor->id, 'appointment_id' => $appointment->id, 'visited_on' => today()]);

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization))->assertRedirect(route('admin.approvals.index'));

        $this->assertSame(0, Patient::withoutTenancy()->withTrashed()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, Appointment::withoutTenancy()->where('organization_id', $organization->id)->count());

        $real = app(RegisterPatient::class)($organization->id, ['name' => 'First Real', 'mobile' => '+919812345678']);
        $this->assertSame('P-000001', $real->patient_code, 'Sample records must not use up real numbers.');
    }

    public function test_approval_needs_the_documents_is_audited_and_tells_the_owner(): void
    {
        Bus::fake([SendMessage::class]);
        config(['messages.whatsapp.token' => 't', 'messages.whatsapp.phone_number_id' => 'p']);
        [$organization, $owner] = $this->pendingClinic();
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'platform');

        $this->from(route('admin.approvals.show', $organization))->post(route('admin.organizations.approve', $organization))->assertSessionHas('error');
        $this->assertSame(OrganizationStatus::PendingVerification, $organization->fresh()->status);

        $this->withProof($organization);
        $this->post(route('admin.organizations.approve', $organization))->assertSessionHas('success');

        $fresh = $organization->fresh();
        $this->assertSame(OrganizationStatus::Approved, $fresh->status);
        $this->assertSame($admin->id, (int) $fresh->reviewed_by);
        $this->assertNotNull(AuditLog::withoutTenancy()->where('action', 'organizations.approved')->where('organization_id', $organization->id)->where('actor_id', $admin->id)->first());
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_approved' && $job->channel === 'email' && $job->to === $owner->email);
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_approved' && $job->channel === 'whatsapp' && $job->to === $owner->mobile);
    }

    public function test_rejecting_needs_a_reason_and_the_practice_can_fix_and_resubmit(): void
    {
        Bus::fake([SendMessage::class]);
        config(['messages.whatsapp.token' => 't', 'messages.whatsapp.phone_number_id' => 'p']);
        [$organization, $owner] = $this->pendingClinic();
        $this->withProof($organization);
        $boss = $this->superAdmin();
        $this->actingAs($boss, 'platform');

        $this->post(route('admin.approvals.reject', $organization), [])->assertSessionHasErrors('reason');
        $this->assertSame(OrganizationStatus::PendingVerification, $organization->fresh()->status);

        $this->post(route('admin.approvals.reject', $organization), ['reason' => 'The proof photo is too blurred.'])->assertRedirect(route('admin.approvals.index'));
        $this->assertSame(OrganizationStatus::Rejected, $organization->fresh()->status);
        $log = AuditLog::withoutTenancy()->where('action', 'organizations.rejected')->firstOrFail();
        $this->assertSame('The proof photo is too blurred.', $log->metadata['reason']);
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_rejected' && $job->channel === 'whatsapp' && $job->variables['reason'] === 'The proof photo is too blurred.');

        $this->actingAs($owner, 'web');
        $this->get(route('app.dashboard'))->assertOk()->assertSee('The proof photo is too blurred.');
        $this->post(route('app.patients.store'), ['name' => 'X', 'mobile' => '9812345678'])->assertSessionHas('error');

        $this->post(route('app.verification.resubmit'))->assertSessionHas('success');
        $this->assertSame(OrganizationStatus::PendingVerification, $organization->fresh()->status);
        $this->assertNotNull($organization->fresh()->resubmitted_at);
    }

    public function test_a_suspended_practice_stays_locked_out_and_restoring_keeps_its_data(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization, $owner] = $this->organizationWithOwner();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'is_demo' => true]);
        $organization->forceFill(['status' => OrganizationStatus::Suspended, 'suspension_reason' => 'Fraud'])->save();

        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertRedirect(route('app.organization.status'));

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization));

        $this->assertSame(OrganizationStatus::Approved, $organization->fresh()->status);
        $this->assertNotNull(Patient::withoutTenancy()->find($patient->id), 'Restoring is not a first approval.');
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_restored');
    }

    public function test_only_people_allowed_can_review(): void
    {
        [$organization] = $this->pendingClinic();
        $this->withProof($organization);
        $sales = PlatformAdmin::factory()->create(['role' => 'sales', 'two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()]);

        $this->actingAs($sales, 'platform');
        $this->get(route('admin.approvals.index'))->assertForbidden();
        $this->post(route('admin.organizations.approve', $organization))->assertForbidden();
        $this->assertSame(OrganizationStatus::PendingVerification, $organization->fresh()->status);
    }

    public function test_the_admin_menu_counts_what_is_waiting_and_the_review_page_opens(): void
    {
        [$organization] = $this->pendingClinic();
        $this->withProof($organization);
        $this->actingAs($this->superAdmin(), 'platform');

        $this->get(route('admin.dashboard'))->assertSee('Pending approvals')->assertSee('aria-label="1 waiting"', false);
        $this->get(route('admin.approvals.index'))->assertOk()->assertSee($organization->name)->assertSee('Ready to review');
        $this->get(route('admin.approvals.show', $organization))->assertOk()->assertSee('proof.pdf');
    }

    public function test_documents_upload_safely_and_cannot_cross_tenants(): void
    {
        Storage::fake('local');
        [$organization, $owner] = $this->pendingClinic();
        [$other, $otherOwner] = $this->pendingClinic();
        $this->actingAs($owner, 'web');

        $this->post(route('app.verification.documents.store'), ['type' => 'clinic_proof', 'document' => UploadedFile::fake()->create('evil.html', 5, 'text/html')])->assertSessionHasErrors('document');
        $this->post(route('app.verification.documents.store'), ['type' => 'clinic_proof', 'document' => UploadedFile::fake()->image('proof.png')])->assertSessionHasNoErrors();

        $document = OrganizationDocument::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('verification/'.$organization->id.'/', $document->path);
        $this->get(route('app.verification.documents.show', $document))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($otherOwner, 'web');
        $this->get(route('app.verification.documents.show', $document))->assertNotFound();
        $this->delete(route('app.verification.documents.destroy', $document))->assertNotFound();
        $this->assertNotNull($other);

        $admin = $this->superAdmin();
        $this->actingAs($admin, 'platform')->get(route('admin.approvals.document', [$organization, $document->id]))->assertOk();
        $this->assertNotNull(AuditLog::withoutTenancy()->where('action', 'organizations.document_viewed')->first());
        $this->get(route('admin.approvals.document', [$other, $document->id]))->assertNotFound();
    }

    public function test_a_solo_doctor_gives_a_registration_number_and_gets_the_verified_badge(): void
    {
        Bus::fake([SendMessage::class]);
        $organization = Organization::factory()->pending()->create(['type' => 'solo_practice']);
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $doctor = $this->addMember($organization, ['owner', 'doctor'], location: $location);

        $this->actingAs($doctor, 'web');
        $this->post(route('app.verification.registration'), [])->assertSessionHasErrors(['registration_number', 'registration_council']);
        $this->post(route('app.verification.registration'), ['registration_number' => 'WB-12345', 'registration_council' => 'West Bengal Medical Council'])->assertSessionHas('success');

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization))->assertSessionHas('success');

        $this->assertSame('verified', DoctorProfile::query()->where('user_id', $doctor->id)->firstOrFail()->verification_status);
    }
}
