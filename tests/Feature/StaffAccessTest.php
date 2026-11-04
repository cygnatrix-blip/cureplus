<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    /**
     * A manager who was given "Manage staff" but is not an owner.
     *
     * @return array{Organization, User, Location, User}
     */
    private function clinicWithManager(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $manager = $this->addMember($organization, ['receptionist'], location: $location);
        $this->actingAs($owner, 'web')->put(route('app.roles.update'), ['grants' => ['receptionist' => ['dashboard.view', 'staff.manage']]])->assertSessionHasNoErrors();
        auth('web')->logout();

        return [$organization, $owner, $location, $manager];
    }

    public function test_a_manager_cannot_invite_an_owner(): void
    {
        Notification::fake();
        [, , $location, $manager] = $this->clinicWithManager();

        $this->actingAs($manager, 'web')
            ->post(route('app.invitations.store'), ['email' => 'friend@example.test', 'role_keys' => ['owner'], 'location_ids' => [$location->id]])
            ->assertSessionHasErrors('role_keys');

        $this->assertSame(0, Invitation::withoutTenancy()->count());
    }

    public function test_a_manager_cannot_make_themselves_an_owner_or_touch_a_real_owner(): void
    {
        [$organization, $owner, $location, $manager] = $this->clinicWithManager();
        $mine = Membership::withoutTenancy()->where('organization_id', $organization->id)->where('user_id', $manager->id)->firstOrFail();
        $theirs = Membership::withoutTenancy()->where('organization_id', $organization->id)->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($manager, 'web');
        $this->put(route('app.staff.update', $mine), ['roles' => ['owner'], 'locations' => [$location->id]])->assertSessionHas('error');
        $this->assertFalse($mine->fresh()->roles()->where('key', 'owner')->exists());

        $this->put(route('app.staff.update', $theirs), ['roles' => ['nurse'], 'locations' => [$location->id]])->assertSessionHas('error');
        $this->assertTrue($theirs->fresh()->roles()->where('key', 'owner')->exists());

        $this->post(route('app.staff.deactivate', $theirs))->assertSessionHas('error');
        $this->assertSame('active', $theirs->fresh()->status);
    }

    public function test_an_owner_can_add_another_owner(): void
    {
        Notification::fake();
        [, $owner, $location] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')
            ->post(route('app.invitations.store'), ['email' => 'partner@example.test', 'role_keys' => ['owner'], 'location_ids' => [$location->id]])
            ->assertSessionHasNoErrors();
    }

    public function test_making_someone_a_doctor_gives_them_a_booking_link(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $owner->forceFill(['name' => 'Asha Sen'])->save();
        $membership = Membership::withoutTenancy()->where('organization_id', $organization->id)->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner, 'web')->put(route('app.staff.update', $membership), ['roles' => ['owner', 'doctor'], 'locations' => [$location->id]])->assertSessionHas('success');

        $this->assertSame('dr-asha-sen', $membership->fresh()->doctor_slug);
    }

    public function test_inviting_the_same_person_again_replaces_the_old_invitation(): void
    {
        Notification::fake();
        [, $owner, $location] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');
        $payload = ['email' => 'dr@example.test', 'role_keys' => ['doctor'], 'location_ids' => [$location->id]];

        $this->post(route('app.invitations.store'), $payload);
        $this->post(route('app.invitations.store'), $payload);

        $this->assertSame(1, Invitation::withoutTenancy()->whereNull('revoked_at')->count());
    }

    public function test_the_invite_form_opens_ready_for_a_doctor(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->get(route('app.staff.index', ['invite' => 'doctor']))
            ->assertOk()
            ->assertSee('How adding a doctor works')
            ->assertSee('value="doctor" checked', false);
    }

    public function test_local_uploads_keep_a_temp_folder_on_windows(): void
    {
        $this->assertContains('TEMP', ServeCommand::$passthroughVariables);
    }

    public function test_only_people_with_reports_access_can_export_all_patients(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $nurse = $this->addMember($organization, ['nurse'], location: $location);

        $this->actingAs($nurse, 'web')->get(route('app.patients.export'))->assertForbidden();
    }

    public function test_sign_in_codes_for_one_email_are_capped_whatever_the_ip(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])->post(route('patient.login.send'), ['email' => 'victim@example.test'])->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->post(route('patient.login.send'), ['email' => 'victim@example.test'])->assertStatus(429);
    }
}
