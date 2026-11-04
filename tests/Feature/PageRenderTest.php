<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Patient;
use App\Services\Billing\BillingService;
use Tests\TestCase;

/**
 * Renders every page with real layouts, which also catches N+1 queries:
 * lazy loading throws outside production.
 */
class PageRenderTest extends TestCase
{
    public function test_all_guest_pages_render(): void
    {
        foreach (['/login', '/login/otp', '/register', '/forgot-password', '/reset-password/abc', '/admin/login'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_all_clinic_pages_render_with_several_records(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $this->addMember($organization, ['nurse'], location: $location);
        $this->addMember($organization, ['doctor', 'receptionist'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner, 'web');

        foreach ([
            route('app.dashboard'), route('app.organizations'), route('app.account'), route('app.two-factor.setup'),
            route('app.locations.index'), route('app.locations.create'), route('app.locations.edit', $location),
            route('app.staff.index'), route('app.staff.edit', $membership), route('app.roles.index'), route('app.audit.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_dashboard_shows_real_numbers_for_each_role(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Waiting Patient']);
        Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);
        $billing = app(BillingService::class);
        $invoice = $billing->createInvoice($organization, $patient, [['description' => 'x', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id]);
        $billing->recordPayment($invoice, 50000, 'cash');

        $ownerView = $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk()->assertSee('Waiting Patient')->assertSee('₹500.00');
        $this->assertSame(1, $ownerView->viewData('waiting'));

        $otherDoctor = $this->addMember($organization, ['doctor'], location: $location);
        $this->actingAs($otherDoctor, 'web')->get(route('app.dashboard'))->assertOk()->assertDontSee('Waiting Patient')->assertSee('Your day');

        $accountant = $this->addMember($organization, ['accountant'], location: $location);
        $this->actingAs($accountant, 'web')->get(route('app.dashboard'))->assertOk()->assertSee('₹500.00')->assertDontSee('Waiting Patient');
    }

    public function test_a_solo_doctor_gets_the_simplified_menu(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $organization->forceFill(['type' => 'solo_practice'])->save();

        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk()->assertSee('Chambers')->assertDontSee('Role permissions');
    }

    public function test_a_receptionist_only_sees_menu_items_they_can_use(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($user, 'web')->get(route('app.dashboard'))->assertOk()->assertDontSee('Audit log')->assertDontSee('Role permissions');
    }

    public function test_all_admin_pages_render_with_several_records(): void
    {
        [$organization] = $this->organizationWithOwner();
        $this->organizationWithOwner();
        $this->addMember($organization, ['nurse']);
        Organization::factory()->pending()->create();

        $this->actingAs($this->superAdmin(), 'platform');

        foreach ([
            route('admin.dashboard'), route('admin.organizations.index'), route('admin.organizations.show', $organization),
            route('admin.audit.index'), route('admin.security.index'), route('admin.admins.index'), route('admin.two-factor.setup'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_error_pages_are_neutral(): void
    {
        $this->get('/no-such-page-here')->assertNotFound()->assertSee('Page not found')->assertDontSee('Laravel');
    }

    public function test_user_input_is_escaped(): void
    {
        [, $owner, $location] = $this->organizationWithOwner();
        $location->forceFill(['name' => '<script>alert(1)</script>'])->saveQuietly();

        $this->actingAs($owner, 'web')->get(route('app.locations.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
