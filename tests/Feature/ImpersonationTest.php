<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    public function test_impersonation_needs_a_reason_shows_a_banner_and_is_audited(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'platform');

        $this->post(route('admin.organizations.impersonate', $organization), [])->assertSessionHasErrors('reason');
        $this->assertGuest('web');

        $this->post(route('admin.organizations.impersonate', $organization), ['reason' => 'Ticket 42'])->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($owner, 'web');

        $this->get(route('app.dashboard'))->assertOk()->assertSee('You are viewing as')->assertSee('Stop viewing');

        $started = AuditLog::withoutTenancy()->where('action', 'impersonation.started')->firstOrFail();
        $this->assertSame('platform_admin', $started->actor_type);
        $this->assertSame($admin->id, $started->actor_id);
        $this->assertSame('Ticket 42', $started->metadata['reason']);

        // What happens during the session is tied back to the admin.
        $this->post(route('app.locations.store'), ['name' => 'Made by support', 'is_active' => '1']);
        $log = AuditLog::withoutTenancy()->where('action', 'locations.created')->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertSame($admin->id, $log->impersonator_id);

        $this->post(route('admin.impersonation.stop'))->assertRedirect(route('admin.organizations.show', $organization));
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertSame(1, AuditLog::withoutTenancy()->where('action', 'impersonation.stopped')->count());
    }

    public function test_impersonating_cannot_take_the_account_over(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($this->superAdmin(), 'platform');
        $this->post(route('admin.organizations.impersonate', $organization), ['reason' => 'Ticket 42']);

        $this->put(route('app.account.password'), ['current_password' => 'Password123', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertForbidden();
        $this->get(route('app.two-factor.setup'))->assertForbidden();
        $this->delete(route('app.account.sessions.revoke', 'x'))->assertForbidden();
        [$other] = $this->organizationWithOwner();
        $this->post(route('app.organizations.switch', $other->uuid))->assertForbidden();

        $this->assertTrue(password_verify('Password123', $owner->fresh()->password));
    }

    public function test_an_organization_without_an_owner_cannot_be_impersonated(): void
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->superAdmin(), 'platform')
            ->from(route('admin.organizations.show', $organization))
            ->post(route('admin.organizations.impersonate', $organization), ['reason' => 'x'])
            ->assertSessionHas('error');

        $this->assertGuest('web');
    }

    public function test_a_clinic_user_cannot_stop_or_start_impersonation(): void
    {
        [$organization] = $this->organizationWithOwner();

        $this->actingAs(User::factory()->create(), 'web');

        $this->post(route('admin.organizations.impersonate', $organization), ['reason' => 'x'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.impersonation.stop'))->assertRedirect(route('admin.login'));
    }
}
