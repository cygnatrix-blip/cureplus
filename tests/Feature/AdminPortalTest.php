<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    public function test_approve_and_suspend_with_a_reason_are_audited(): void
    {
        $organization = Organization::factory()->pending()->create();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.organizations.approve', $organization))->assertRedirect();
        $this->assertSame(OrganizationStatus::Active, $organization->fresh()->status);

        $this->post(route('admin.organizations.suspend', $organization), [])->assertSessionHasErrors('reason');
        $this->post(route('admin.organizations.suspend', $organization), ['reason' => 'Non-payment'])->assertRedirect();
        $this->assertSame(OrganizationStatus::Suspended, $organization->fresh()->status);

        $this->assertSame(2, AuditLog::withoutTenancy()->where('action', 'organizations.updated')->where('organization_id', $organization->id)->count());
    }

    public function test_organizations_can_be_searched_filtered_and_exported(): void
    {
        Organization::factory()->create(['name' => 'Alpha Clinic']);
        Organization::factory()->pending()->create(['name' => 'Beta Clinic']);
        $this->actingAs($this->superAdmin(), 'platform');

        $this->get(route('admin.organizations.index', ['q' => 'Alpha']))->assertOk()->assertSee('Alpha Clinic')->assertDontSee('Beta Clinic');
        $this->get(route('admin.organizations.index', ['status' => 'pending']))->assertOk()->assertSee('Beta Clinic')->assertDontSee('Alpha Clinic');

        $export = $this->get(route('admin.organizations.export'));
        $export->assertOk();
        $this->assertStringContainsString('Alpha Clinic', $export->streamedContent());
    }

    public function test_organization_page_masks_contact_details(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();

        $this->actingAs($this->superAdmin(), 'platform')
            ->get(route('admin.organizations.show', $organization))
            ->assertOk()
            ->assertSee($owner->name)
            ->assertDontSee((string) $owner->email);
    }

    public function test_the_platform_audit_log_spans_organizations_and_filters(): void
    {
        [$a] = $this->organizationWithOwner();
        [$b] = $this->organizationWithOwner();
        AuditLog::withoutTenancy()->create(['organization_id' => $a->id, 'action' => 'alpha.action', 'actor_type' => 'user', 'created_at' => now()]);
        AuditLog::withoutTenancy()->create(['organization_id' => $b->id, 'action' => 'beta.action', 'actor_type' => 'platform_admin', 'created_at' => now()]);

        $this->actingAs($this->superAdmin(), 'platform');

        $this->get(route('admin.audit.index'))->assertOk()->assertSee('alpha.action')->assertSee('beta.action');
        $this->get(route('admin.audit.index', ['actor' => 'platform_admin']))->assertOk()->assertDontSee('alpha.action');
        $this->assertStringContainsString('alpha.action', $this->get(route('admin.audit.export'))->streamedContent());
    }

    public function test_force_logout_ends_a_users_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'target', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.security.force-logout', $user))->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'target']);
        $this->assertSame(1, SecurityEvent::query()->where('type', 'forced_logout')->count());
    }

    public function test_admins_can_be_added_and_deactivated_but_not_self_deactivated(): void
    {
        $me = $this->superAdmin();
        $this->actingAs($me, 'platform');

        $this->post(route('admin.admins.store'), [
            'name' => 'Support One', 'email' => 'Support@Example.test', 'role' => 'support',
            'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertSessionHas('success');

        $new = PlatformAdmin::query()->where('email', 'support@example.test')->firstOrFail();
        $this->assertSame('support', $new->role);
        DB::table('sessions')->insert(['id' => 'new-admin', 'platform_admin_id' => $new->id, 'payload' => '', 'last_activity' => time()]);

        $this->post(route('admin.admins.deactivate', $new))->assertSessionHas('success');
        $this->assertFalse($new->fresh()->isActive());
        $this->assertDatabaseMissing('sessions', ['id' => 'new-admin']);

        $this->post(route('admin.admins.deactivate', $me))->assertSessionHas('error');
        $this->assertTrue($me->fresh()->isActive());
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'platform')
            ->post(route('admin.admins.store'), ['name' => 'x', 'email' => 'x@example.test', 'role' => 'god', 'password' => 'Password123', 'password_confirmation' => 'Password123'])
            ->assertSessionHasErrors('role');
    }
}
