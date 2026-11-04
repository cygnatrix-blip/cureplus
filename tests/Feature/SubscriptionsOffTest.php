<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Services\Billing\FeatureGate;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Tests\TestCase;

class SubscriptionsOffTest extends TestCase
{
    public function test_subscription_pages_do_not_exist_for_clinics_or_admins(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web');
        $this->get(route('app.subscription.index'))->assertNotFound();
        $this->post(route('app.subscription.checkout'), ['plan_id' => 1])->assertNotFound();
        $this->get(route('app.dashboard'))->assertOk()->assertDontSee('Subscription');

        $this->actingAs($this->superAdmin(), 'platform');
        $this->get(route('admin.plans.index'))->assertNotFound();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Plans and subscriptions')->assertDontSee('Monthly recurring revenue');
    }

    public function test_nothing_is_limited_or_gated_by_an_old_plan(): void
    {
        $this->seed(PlanSeeder::class);
        [$organization] = $this->organizationWithOwner();
        $starter = Plan::query()->orderBy('price_paise')->firstOrFail();
        $starter->forceFill(['limits' => ['doctors' => 0, 'staff' => 0, 'locations' => 0, 'messages_per_month' => 0], 'features' => []])->save();
        OrganizationSubscription::withoutTenancy()->create(['organization_id' => $organization->id, 'plan_id' => $starter->id, 'status' => 'trial', 'trial_ends_at' => now()->subYear()]);

        $this->assertNull(app(PlanLimits::class)->limit($organization->id, 'doctors'));
        $this->assertTrue(app(PlanLimits::class)->canSendPaidMessage($organization->id));
        $this->assertTrue(app(FeatureGate::class)->enabled($organization->id, 'custom_domain'));
    }

    public function test_an_expired_trial_cannot_suspend_anyone_while_switched_off(): void
    {
        $this->seed(PlanSeeder::class);
        [$organization] = $this->organizationWithOwner();
        OrganizationSubscription::withoutTenancy()->create(['organization_id' => $organization->id, 'plan_id' => Plan::query()->firstOrFail()->id, 'status' => 'trial', 'trial_ends_at' => now()->subYear()]);

        app(SubscriptionService::class)->enforce();

        $this->assertTrue($organization->fresh()->isActive());
        $this->assertSame('trial', OrganizationSubscription::withoutTenancy()->firstOrFail()->status);
        $this->assertSame(1, Membership::withoutTenancy()->count());
    }

    public function test_when_switched_back_on_the_trial_starts_on_approval_not_signup(): void
    {
        config(['plans.enabled' => true]);
        $this->seed(PlanSeeder::class);
        $organization = Organization::factory()->pending()->create();
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $this->addMember($organization, ['owner'], location: $location);
        OrganizationDocument::factory()->create(['organization_id' => $organization->id]);

        $this->assertSame(0, OrganizationSubscription::withoutTenancy()->count());

        $this->travel(5)->days();
        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization))->assertSessionHas('success');

        $subscription = OrganizationSubscription::withoutTenancy()->firstOrFail();
        $plan = Plan::query()->whereKey($subscription->plan_id)->firstOrFail();
        $this->assertTrue($subscription->trial_ends_at->isSameDay(now()->addDays($plan->trial_days)));
    }
}
