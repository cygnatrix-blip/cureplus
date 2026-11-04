<?php

namespace Tests\Feature;

use App\Jobs\SendMessage;
use App\Models\AuditLog;
use App\Models\DoctorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DoctorApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cureplus.organizations.doctors_need_approval' => true]);
    }

    /**
     * @return array{Organization, User, User}
     */
    private function clinicWithInvitedDoctor(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr New']), $location);
        Membership::withoutTenancy()->where('user_id', $doctor->id)->update(['doctor_slug' => 'dr-new']);

        return [$organization, $owner, $doctor];
    }

    private function details(): array
    {
        return ['registration_number' => 'WB-555', 'registration_council' => 'West Bengal Medical Council'];
    }

    public function test_patients_cannot_find_or_book_a_doctor_until_checked(): void
    {
        [$organization, , $doctor] = $this->clinicWithInvitedDoctor();

        $this->get('/'.$organization->slug)->assertOk()->assertDontSee('Dr New');
        $this->get(route('booking.doctor', [$organization->slug, 'dr-new']))->assertNotFound();

        DoctorProfile::query()->create(['user_id' => $doctor->id] + $this->details())->forceFill(['verification_status' => 'verified'])->save();

        $this->get('/'.$organization->slug)->assertOk()->assertSee('Dr New');
        $this->get(route('booking.doctor', [$organization->slug, 'dr-new']))->assertOk();
    }

    public function test_sending_details_tells_the_super_admin_and_they_can_approve(): void
    {
        Bus::fake([SendMessage::class]);
        PlatformAdmin::factory()->create(['email' => 'boss@example.test', 'role' => 'super_admin']);
        [$organization, , $doctor] = $this->clinicWithInvitedDoctor();

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.update'), $this->details())->assertSessionHas('success');
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'doctor_verification_requested' && $job->to === 'boss@example.test');

        $admin = $this->superAdmin();
        $this->actingAs($admin, 'platform');
        $this->get(route('admin.dashboard'))->assertSee('aria-label="1 waiting"', false);
        $this->get(route('admin.approvals.index'))->assertSee('Dr New')->assertSee('WB-555')->assertSee($organization->name);

        $profile = DoctorProfile::query()->where('user_id', $doctor->id)->firstOrFail();
        $this->post(route('admin.doctors.verify', $profile), ['status' => 'verified'])->assertSessionHas('success');

        $this->assertSame('verified', $profile->fresh()->verification_status);
        $this->assertNotNull(AuditLog::withoutTenancy()->where('action', 'doctor_profiles.verified')->where('actor_id', $admin->id)->first());
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'doctor_verified' && $job->to === $doctor->email);
        auth('platform')->logout();
        $this->get('/'.$organization->slug)->assertSee('Dr New');
    }

    public function test_rejecting_needs_a_reason_and_the_doctor_can_fix_it(): void
    {
        Bus::fake([SendMessage::class]);
        [, , $doctor] = $this->clinicWithInvitedDoctor();
        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.update'), $this->details());
        $profile = DoctorProfile::query()->where('user_id', $doctor->id)->firstOrFail();

        $this->actingAs($this->superAdmin(), 'platform');
        $this->post(route('admin.doctors.verify', $profile), ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $this->post(route('admin.doctors.verify', $profile), ['status' => 'rejected', 'reason' => 'This number is not on the register.'])->assertSessionHas('success');
        $this->assertSame('rejected', $profile->fresh()->verification_status);
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'doctor_rejected' && $job->variables['reason'] === 'This number is not on the register.');

        $this->actingAs($doctor, 'web');
        $this->get(route('app.account'))->assertSee('This number is not on the register.');
        $this->post(route('app.doctor-profile.update'), $this->details());
        $this->assertSame('unverified', $profile->fresh()->verification_status);
    }

    public function test_a_doctor_with_no_registration_number_cannot_be_approved(): void
    {
        [, , $doctor] = $this->clinicWithInvitedDoctor();
        $profile = DoctorProfile::query()->create(['user_id' => $doctor->id]);

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.doctors.verify', $profile), ['status' => 'verified'])->assertSessionHas('error');

        $this->assertSame('unverified', $profile->fresh()->verification_status);
    }

    public function test_changing_the_number_or_council_removes_the_doctor_from_booking_until_checked_again(): void
    {
        [$organization, , $doctor] = $this->clinicWithInvitedDoctor();
        DoctorProfile::query()->create(['user_id' => $doctor->id] + $this->details())->forceFill(['verification_status' => 'verified'])->save();

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.update'), ['registration_number' => 'WB-555', 'registration_council' => 'Delhi Medical Council']);
        auth('web')->logout();

        $this->get('/'.$organization->slug)->assertDontSee('Dr New');
    }

    public function test_only_super_admins_decide(): void
    {
        [, , $doctor] = $this->clinicWithInvitedDoctor();
        $profile = DoctorProfile::query()->create(['user_id' => $doctor->id] + $this->details());
        $support = PlatformAdmin::factory()->create(['role' => 'support', 'two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()]);

        $this->actingAs($support, 'platform')->post(route('admin.doctors.verify', $profile), ['status' => 'verified'])->assertForbidden();
    }

    public function test_the_clinic_sees_where_each_doctor_stands(): void
    {
        [, $owner, $doctor] = $this->clinicWithInvitedDoctor();

        $this->actingAs($owner, 'web')->get(route('app.staff.index'))->assertOk()->assertSee('Needs registration number');

        DoctorProfile::query()->create(['user_id' => $doctor->id] + $this->details());
        $this->get(route('app.staff.index'))->assertSee('Awaiting check');
    }
}
