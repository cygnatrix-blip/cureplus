<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationRolePermission;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\Visit;
use App\Tenancy\BelongsToOrganization;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_every_table_with_organization_id_has_a_tenant_scoped_model(): void
    {
        $scopedTables = collect(glob(app_path('Models/*.php')))
            ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
            ->filter(fn (string $class) => in_array(BelongsToOrganization::class, class_uses_recursive($class), true))
            ->map(fn (string $class) => (new $class)->getTable());

        foreach (Schema::getTables() as $table) {
            if (Schema::hasColumn($table['name'], 'organization_id')) {
                $this->assertContains($table['name'], $scopedTables, "{$table['name']} has organization_id but no model using BelongsToOrganization.");
            }
        }
    }

    public function test_queries_with_no_tenant_return_nothing(): void
    {
        [$organization] = $this->organizationWithOwner();

        $this->assertSame(0, Location::query()->count());
        $this->assertSame(1, Location::withoutTenancy()->where('organization_id', $organization->id)->count());
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function scopedModels(): array
    {
        return [
            'locations' => [Location::class],
            'memberships' => [Membership::class],
            'invitations' => [Invitation::class],
            'role overrides' => [OrganizationRolePermission::class],
            'audit logs' => [AuditLog::class],
            'patients' => [Patient::class],
            'appointments' => [Appointment::class],
            'visits' => [Visit::class],
            'invoices' => [Invoice::class],
            'support tickets' => [SupportTicket::class],
        ];
    }

    /**
     * @param  class-string  $model
     */
    #[DataProvider('scopedModels')]
    public function test_a_tenant_only_sees_its_own_rows(string $model): void
    {
        [$a, $ownerA] = $this->organizationWithOwner();
        [$b, $ownerB] = $this->organizationWithOwner();

        foreach ([[$a, $ownerA], [$b, $ownerB]] as [$organization, $owner]) {
            $this->makeRow($model, $organization, $owner->id);
        }

        app(TenantContext::class)->set($a);
        $rows = $model::query()->get();

        $this->assertGreaterThan(0, $rows->count());
        $this->assertSame([$a->id], $rows->pluck('organization_id')->unique()->values()->all());
    }

    public function test_a_row_cannot_be_created_for_another_tenant(): void
    {
        [$a] = $this->organizationWithOwner();
        [$b] = $this->organizationWithOwner();

        app(TenantContext::class)->set($a);

        $this->expectException(LogicException::class);
        Location::create(['organization_id' => $b->id, 'name' => 'Sneaky']);
    }

    public function test_a_row_cannot_be_created_without_a_tenant(): void
    {
        $this->expectException(LogicException::class);
        Location::create(['name' => 'Orphan']);
    }

    public function test_a_row_cannot_move_between_tenants(): void
    {
        [, , $location] = $this->organizationWithOwner();
        [$b] = $this->organizationWithOwner();

        $this->expectException(LogicException::class);
        $location->update(['organization_id' => $b->id]);
    }

    public function test_tenant_resolves_before_route_model_binding(): void
    {
        $priority = app(Kernel::class)->getMiddlewarePriority();

        $this->assertLessThan(
            array_search(SubstituteBindings::class, $priority, true),
            array_search(ResolveTenant::class, $priority, true),
        );
    }

    public function test_another_tenants_records_are_404_on_every_route(): void
    {
        [, $ownerA] = $this->organizationWithOwner();
        [$b, $ownerB, $locationB] = $this->organizationWithOwner();
        $membershipB = Membership::withoutTenancy()->where('user_id', $ownerB->id)->firstOrFail();
        $invitationB = Invitation::withoutTenancy()->create([
            'organization_id' => $b->id, 'email' => 'x@example.test', 'token_hash' => Str::random(64),
            'role_keys' => ['nurse'], 'location_ids' => [$locationB->id], 'invited_by' => $ownerB->id, 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($ownerA, 'web');

        $this->get(route('app.locations.edit', $locationB->id))->assertNotFound();
        $this->put(route('app.locations.update', $locationB->id), ['name' => 'Hacked'])->assertNotFound();
        $this->delete(route('app.locations.destroy', $locationB->id))->assertNotFound();
        $this->get(route('app.staff.edit', $membershipB->id))->assertNotFound();
        $this->put(route('app.staff.update', $membershipB->id), ['roles' => ['nurse']])->assertNotFound();
        $this->post(route('app.staff.deactivate', $membershipB->id))->assertNotFound();
        $this->delete(route('app.invitations.destroy', $invitationB->id))->assertNotFound();

        $this->assertSame($locationB->name, Location::withoutTenancy()->findOrFail($locationB->id)->name);
        $this->assertSame('active', Membership::withoutTenancy()->findOrFail($membershipB->id)->status);
    }

    public function test_lists_never_show_another_tenants_data(): void
    {
        [, $ownerA] = $this->organizationWithOwner();
        [, $ownerB, $locationB] = $this->organizationWithOwner();
        $locationB->forceFill(['name' => 'Tenant B Secret Branch'])->saveQuietly();

        $this->actingAs($ownerA, 'web');

        $this->get(route('app.locations.index'))->assertOk()->assertDontSee('Tenant B Secret Branch');
        $this->get(route('app.staff.index'))->assertOk()->assertDontSee($ownerB->name);
    }

    public function test_another_tenants_location_cannot_be_assigned(): void
    {
        [$a, $ownerA] = $this->organizationWithOwner();
        [, , $locationB] = $this->organizationWithOwner();
        $membership = Membership::withoutTenancy()->where('user_id', $this->addMember($a, ['nurse'])->id)->firstOrFail();

        $this->actingAs($ownerA, 'web');

        $this->from(route('app.staff.index'))
            ->post(route('app.invitations.store'), ['email' => 'new@example.test', 'role_keys' => ['nurse'], 'location_ids' => [$locationB->id]])
            ->assertSessionHasErrors('location_ids.0');

        $this->from(route('app.staff.index'))
            ->put(route('app.staff.update', $membership->id), ['roles' => ['nurse'], 'locations' => [$locationB->id]])
            ->assertSessionHasErrors('locations.0');
    }

    public function test_the_tenant_does_not_outlive_its_request(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk();

        $this->assertFalse(app(TenantContext::class)->has());
    }

    public function test_run_restores_the_previous_tenant(): void
    {
        [$a] = $this->organizationWithOwner();
        [$b] = $this->organizationWithOwner();
        $context = app(TenantContext::class);
        $context->set($a);

        $this->assertSame($b->id, $context->run($b, fn () => $context->id()));
        $this->assertSame($a->id, $context->id());
    }

    private function makeClinicalRow(string $model, Organization $organization, int $userId): void
    {
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $location = Location::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();

        $model::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id,
            'patient_id' => $patient->id, 'doctor_user_id' => $userId,
        ]);
    }

    private function makeRow(string $model, Organization $organization, int $userId): void
    {
        match ($model) {
            Location::class, Membership::class => null, // organizationWithOwner already made them.
            Invitation::class => Invitation::withoutTenancy()->create([
                'organization_id' => $organization->id, 'email' => Str::random(8).'@example.test', 'token_hash' => Str::random(64),
                'role_keys' => ['nurse'], 'location_ids' => [], 'invited_by' => $userId, 'expires_at' => now()->addDay(),
            ]),
            OrganizationRolePermission::class => OrganizationRolePermission::withoutTenancy()->create([
                'organization_id' => $organization->id, 'role_id' => Role::query()->firstWhere('key', 'nurse')->id,
                'permission_id' => Permission::query()->firstOrFail()->id, 'granted' => true,
            ]),
            Patient::class => Patient::factory()->create(['organization_id' => $organization->id]),
            SupportTicket::class => SupportTicket::factory()->create(['organization_id' => $organization->id]),
            Invoice::class => Invoice::factory()->create([
                'organization_id' => $organization->id,
                'location_id' => Location::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail()->id,
                'patient_id' => Patient::factory()->create(['organization_id' => $organization->id])->id,
            ]),
            Appointment::class, Visit::class => $this->makeClinicalRow($model, $organization, $userId),
            AuditLog::class => AuditLog::withoutTenancy()->create([
                'organization_id' => $organization->id, 'action' => 'test', 'created_at' => now(),
            ]),
        };
    }
}
