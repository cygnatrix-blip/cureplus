<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrganizationSwitchTest extends TestCase
{
    public function test_a_doctor_in_two_organizations_chooses_then_switches(): void
    {
        [$a, , $locationA] = $this->organizationWithOwner();
        [$b, , $locationB] = $this->organizationWithOwner();
        $doctor = User::factory()->create();
        $this->addMember($a, ['doctor'], $doctor, $locationA);
        $this->addMember($b, ['doctor'], $doctor, $locationB);

        $this->actingAs($doctor, 'web');

        $this->get(route('app.dashboard'))->assertRedirect(route('app.organizations'));
        $this->get(route('app.organizations'))->assertOk()->assertSee($a->name)->assertSee($b->name);

        $this->post(route('app.organizations.switch', $a->uuid))->assertRedirect(route('app.dashboard'));
        $this->get(route('app.dashboard'))->assertOk()->assertSee($a->name)->assertSee($locationA->name)->assertDontSee($b->name);

        $this->post(route('app.organizations.switch', $b->uuid));
        $this->get(route('app.dashboard'))->assertOk()->assertSee($b->name)->assertSee($locationB->name);
    }

    public function test_nobody_can_switch_into_an_organization_they_do_not_belong_to(): void
    {
        [, $owner] = $this->organizationWithOwner();
        [$other] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.organizations.switch', $other->uuid))->assertNotFound();
    }

    public function test_a_forged_session_value_does_not_grant_access(): void
    {
        [$mine, $owner] = $this->organizationWithOwner();
        [$other] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')
            ->withSession([ResolveTenant::SESSION_ORGANIZATION => $other->id])
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee($mine->name)
            ->assertDontSee($other->name);
    }

    public function test_a_suspended_organization_locks_its_members_out_but_not_their_other_clinic(): void
    {
        [$a, , $locationA] = $this->organizationWithOwner();
        [$b, , $locationB] = $this->organizationWithOwner();
        $doctor = User::factory()->create();
        $this->addMember($a, ['doctor'], $doctor, $locationA);
        $this->addMember($b, ['doctor'], $doctor, $locationB);
        $a->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($doctor, 'web');

        $this->withSession([ResolveTenant::SESSION_ORGANIZATION => $a->id])->get(route('app.dashboard'))->assertRedirect(route('app.organization.status'));
        $this->get(route('app.organization.status'))->assertOk()->assertSee('suspended');

        $this->post(route('app.organizations.switch', $b->uuid));
        $this->get(route('app.dashboard'))->assertOk()->assertSee($b->name);
    }

    public function test_staff_can_only_work_at_their_assigned_locations(): void
    {
        [$organization, , $assigned] = $this->organizationWithOwner();
        $other = Location::factory()->create(['organization_id' => $organization->id, 'name' => 'Other Branch']);
        $nurse = $this->addMember($organization, ['nurse'], location: $assigned);

        $this->actingAs($nurse, 'web');

        $this->from(route('app.dashboard'))->post(route('app.location.switch'), ['location_id' => $other->id])->assertForbidden();
        $this->post(route('app.location.switch'), ['location_id' => $assigned->id])->assertRedirect();
        $this->get(route('app.dashboard'))->assertOk()->assertSee($assigned->name);
    }

    public function test_a_location_from_another_organization_cannot_be_selected(): void
    {
        [, $owner] = $this->organizationWithOwner();
        [, , $foreign] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.location.switch'), ['location_id' => $foreign->id])->assertNotFound();
    }

    public function test_context_exposes_the_active_organization_during_the_request(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $seen = null;
        Route::middleware(['web', 'auth:web', 'tenant'])->get('/_probe', function () use (&$seen) {
            $seen = app(TenantContext::class)->id();

            return 'ok';
        });

        $this->actingAs($owner, 'web')->get('/_probe')->assertOk();

        $this->assertSame($organization->id, $seen);
        $this->assertInstanceOf(Organization::class, $organization);
    }
}
