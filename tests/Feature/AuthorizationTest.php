<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationRolePermission;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /**
     * Pages any signed-in member of the portal may reach, on purpose.
     */
    private const OPEN_ROUTES = ['app.location.switch', 'admin.dashboard'];

    public function test_every_tenant_and_platform_route_declares_a_permission(): void
    {
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $isTenantRoute = in_array('tenant', $middleware, true);
            $isAdminRoute = str_starts_with((string) $route->getName(), 'admin.') && in_array('two-factor', $middleware, true);

            if (! $isTenantRoute && ! $isAdminRoute) {
                continue;
            }

            if (in_array($route->getName(), self::OPEN_ROUTES, true)) {
                continue;
            }

            $this->assertNotEmpty(
                array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')),
                "Route {$route->getName()} has no can: middleware."
            );
        }
    }

    public function test_every_portal_route_requires_authentication(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'app.') && ! (str_starts_with($name, 'admin.') && ! str_starts_with($name, 'admin.login'))) {
                continue;
            }

            $this->assertNotEmpty(
                array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'auth:')),
                "Route {$name} has no auth middleware."
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function restrictedRoles(): array
    {
        return [
            'receptionist' => ['receptionist'],
            'nurse' => ['nurse'],
            'accountant' => ['accountant'],
            'doctor' => ['doctor'],
        ];
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_owners_cannot_reach_owner_pages(string $role): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, [$role], location: $location);

        $this->actingAs($user, 'web');

        $this->get(route('app.staff.index'))->assertForbidden();
        $this->get(route('app.locations.index'))->assertForbidden();
        $this->get(route('app.locations.edit', $location))->assertForbidden();
        $this->get(route('app.roles.index'))->assertForbidden();
        $this->get(route('app.audit.index'))->assertForbidden();
        $this->post(route('app.invitations.store'), [])->assertForbidden();
    }

    public function test_an_owner_can_grant_and_revoke_a_permission_for_a_role(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $nurse = $this->addMember($organization, ['nurse'], location: $location);

        $this->actingAs($nurse, 'web')->get(route('app.audit.index'))->assertForbidden();

        $defaults = Role::query()->firstWhere('key', 'nurse')->permissions()->pluck('key')->all();
        $this->actingAs($owner, 'web')
            ->put(route('app.roles.update'), ['grants' => ['nurse' => [...$defaults, 'audit.view']]])
            ->assertRedirect();

        $this->actingAs($nurse, 'web')->get(route('app.audit.index'))->assertOk();

        $this->actingAs($owner, 'web')->put(route('app.roles.update'), ['grants' => ['nurse' => $defaults]]);

        $this->actingAs($nurse, 'web')->get(route('app.audit.index'))->assertForbidden();
        $this->assertSame(0, OrganizationRolePermission::withoutTenancy()->where('organization_id', $organization->id)->count());
    }

    public function test_a_permission_toggle_only_affects_that_organization(): void
    {
        [$a, $ownerA, $locationA] = $this->organizationWithOwner();
        [$b, , $locationB] = $this->organizationWithOwner();
        $nurseA = $this->addMember($a, ['nurse'], location: $locationA);
        $nurseB = $this->addMember($b, ['nurse'], location: $locationB);

        $this->actingAs($ownerA, 'web')->put(route('app.roles.update'), [
            'grants' => ['nurse' => ['dashboard.view', 'audit.view']],
        ]);

        $this->actingAs($nurseA, 'web')->get(route('app.audit.index'))->assertOk();
        $this->actingAs($nurseB, 'web')->get(route('app.audit.index'))->assertForbidden();
    }

    public function test_owner_permissions_cannot_be_edited(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->put(route('app.roles.update'), [
            'grants' => ['owner' => []],
        ]);

        $this->assertSame(0, OrganizationRolePermission::withoutTenancy()->count());
        $this->actingAs($owner, 'web')->get(route('app.roles.index'))->assertOk();
    }

    public function test_roles_combine(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['nurse', 'accountant'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $user->id)->with('roles')->firstOrFail();
        $resolver = app(PermissionResolver::class);

        $this->assertTrue($resolver->allows($membership, 'vitals.manage')); // From nurse.
        $this->assertTrue($resolver->allows($membership, 'reports.view')); // From accountant.
        $this->assertFalse($resolver->allows($membership, 'staff.manage')); // From neither.
    }

    public function test_the_last_owner_cannot_lose_access(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $membership = Membership::withoutTenancy()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner, 'web');

        $this->from(route('app.staff.index'))->post(route('app.staff.deactivate', $membership))->assertSessionHas('error');
        $this->from(route('app.staff.index'))->put(route('app.staff.update', $membership), ['roles' => ['nurse']])->assertSessionHas('error');

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue($membership->fresh()->roles()->where('key', 'owner')->exists());
    }

    public function test_removing_a_member_ends_their_access_on_the_next_request(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $nurse = $this->addMember($organization, ['nurse'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $nurse->id)->firstOrFail();

        $this->actingAs($nurse, 'web')->get(route('app.dashboard'))->assertOk();

        $this->actingAs($owner, 'web')->post(route('app.staff.deactivate', $membership));

        $this->actingAs($nurse, 'web')->get(route('app.dashboard'))->assertRedirect(route('app.no-organization'));
    }

    public function test_a_clinic_user_has_no_way_into_the_admin_portal(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web');

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertFalse($owner->can('platform.organizations.view'));
    }

    public function test_a_platform_admin_holds_no_tenant_permissions(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'platform');

        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
        $this->assertFalse($admin->can('staff.manage'));
    }

    public function test_platform_roles_limit_what_admins_can_do(): void
    {
        $organization = Organization::factory()->pending()->create();

        $this->actingAs($this->superAdmin('support'), 'platform');

        $this->get(route('admin.organizations.index'))->assertOk();
        $this->post(route('admin.organizations.approve', $organization))->assertForbidden();
        $this->get(route('admin.admins.index'))->assertForbidden();
        $this->get(route('admin.security.index'))->assertForbidden();

        $this->actingAs($this->superAdmin('finance'), 'platform');
        $this->post(route('admin.organizations.impersonate', $organization), ['reason' => 'x'])->assertForbidden();
    }

    public function test_every_config_permission_is_seeded(): void
    {
        $this->assertSame(count(config('rbac.permissions')), Permission::query()->count());
        $this->assertSame(count(config('rbac.roles')), Role::query()->count());
    }
}
