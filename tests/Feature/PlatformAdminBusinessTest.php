<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Models\Announcement;
use App\Models\Coupon;
use App\Models\FeatureFlag;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\Template;
use App\Services\Messaging\Notifier;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakeSms;
use Tests\TestCase;

class PlatformAdminBusinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['plans.enabled' => true]);
    }

    public function test_admin_pages_render_and_are_limited_by_role(): void
    {
        $this->seed(PlanSeeder::class);
        $this->actingAs($this->superAdmin(), 'platform');

        foreach (['admin.plans.index', 'admin.feature-flags.index', 'admin.support.index', 'admin.announcements.index', 'admin.templates.index', 'admin.reports.index'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->actingAs($this->superAdmin('support'), 'platform');
        $this->get(route('admin.support.index'))->assertOk();
        $this->get(route('admin.plans.index'))->assertForbidden();
        $this->get(route('admin.templates.index'))->assertForbidden();
    }

    public function test_a_plan_can_be_created_and_edited(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.plans.store'), ['code' => 'gold', 'name' => 'Gold', 'price' => '999', 'billing_interval' => 'month', 'trial_days' => 7, 'limit_doctors' => 2, 'features' => ['billing'], 'is_active' => 1])->assertSessionHas('success');

        $plan = Plan::query()->where('code', 'gold')->firstOrFail();
        $this->assertSame(99900, $plan->price_paise);
        $this->assertSame(2, $plan->limits['doctors']);
        $this->assertNull($plan->limits['staff']);

        $this->put(route('admin.plans.update', $plan), ['name' => 'Gold+', 'price' => '1999', 'billing_interval' => 'year', 'trial_days' => 0])->assertSessionHas('success');
        $this->assertSame('Gold+', $plan->fresh()->name);
        $this->assertFalse($plan->fresh()->is_active);
    }

    public function test_coupons_and_admin_plan_changes(): void
    {
        $this->seed(PlanSeeder::class);
        [$organization] = $this->organizationWithOwner();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.coupons.store'), ['code' => 'launch', 'extra_trial_days' => 30])->assertSessionHas('success');
        $this->assertSame(30, Coupon::query()->where('code', 'LAUNCH')->value('extra_trial_days'));

        $plan = Plan::query()->where('code', 'clinic')->firstOrFail();
        $this->post(route('admin.subscriptions.change-plan', $organization), ['plan_id' => $plan->id])->assertSessionHas('success');
        $this->assertSame($plan->id, OrganizationSubscription::withoutTenancy()->where('organization_id', $organization->id)->value('plan_id'));
    }

    public function test_a_flag_needs_a_valid_target(): void
    {
        $this->seed(PlanSeeder::class);
        [$organization] = $this->organizationWithOwner();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->from(route('admin.feature-flags.index'))->post(route('admin.feature-flags.store'), ['feature' => 'reports', 'is_enabled' => 0])->assertSessionHasErrors('plan_code');
        $this->from(route('admin.feature-flags.index'))->post(route('admin.feature-flags.store'), ['feature' => 'reports', 'is_enabled' => 0, 'organization_slug' => 'nope'])->assertSessionHasErrors('organization_slug');
        $this->post(route('admin.feature-flags.store'), ['feature' => 'reports', 'is_enabled' => 0, 'organization_slug' => $organization->slug])->assertSessionHas('success');
        $this->post(route('admin.feature-flags.store'), ['feature' => 'reports', 'is_enabled' => 1, 'plan_code' => 'starter'])->assertSessionHas('success');

        $this->assertSame(2, FeatureFlag::withoutTenancy()->count());
    }

    public function test_a_ticket_goes_back_and_forth_and_stays_with_its_organization(): void
    {
        [, $owner] = $this->organizationWithOwner();
        [, $stranger] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->post(route('app.support.store'), ['subject' => 'Help with Razorpay', 'category' => 'razorpay', 'body' => 'Please help'])->assertRedirect();
        $ticket = SupportTicket::withoutTenancy()->firstOrFail();

        $this->actingAs($stranger, 'web')->get(route('app.support.show', $ticket->id))->assertNotFound();
        $this->get(route('app.support.index'))->assertOk()->assertDontSee('Help with Razorpay');

        $this->actingAs($this->superAdmin('support'), 'platform');
        $this->get(route('admin.support.show', $ticket->id))->assertOk()->assertSee('Please help');
        $this->post(route('admin.support.reply', $ticket->id), ['body' => 'Happy to', 'close' => 1])->assertSessionHas('success');
        $this->assertSame('closed', SupportTicket::withoutTenancy()->find($ticket->id)->status);

        $this->actingAs($owner, 'web')->get(route('app.support.show', $ticket->id))->assertOk()->assertSee('Happy to');
        $this->post(route('app.support.reply', $ticket->id), ['body' => 'Still stuck'])->assertRedirect();
        $this->assertSame('open', SupportTicket::withoutTenancy()->find($ticket->id)->status);
    }

    public function test_the_ask_for_help_link_prefills_a_ticket(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->get(route('app.support.index', ['subject' => 'Help me set up Razorpay', 'category' => 'razorpay']))->assertOk()->assertSee('Help me set up Razorpay');
        $this->get(route('app.integrations.index'))->assertOk()->assertSee('Ask us to help');
    }

    public function test_announcements_show_until_dismissed_and_only_where_targeted(): void
    {
        [$target, $owner] = $this->organizationWithOwner();
        [, $other] = $this->organizationWithOwner();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.announcements.store'), ['title' => 'Planned maintenance', 'body' => 'Sunday night', 'organization_slugs' => $target->slug])->assertSessionHas('success');
        $this->from(route('admin.announcements.index'))->post(route('admin.announcements.store'), ['title' => 'x', 'body' => 'y', 'organization_slugs' => 'nope'])->assertSessionHasErrors('organization_slugs');

        $this->actingAs($other, 'web')->get(route('app.dashboard'))->assertOk()->assertDontSee('Planned maintenance');
        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk()->assertSee('Planned maintenance');

        $this->post(route('app.announcements.dismiss', Announcement::query()->firstOrFail()))->assertRedirect();
        $this->get(route('app.dashboard'))->assertOk()->assertDontSee('Planned maintenance');
    }

    public function test_message_wording_can_be_changed_and_reset(): void
    {
        $sms = new FakeSms;
        $this->app->instance(SmsProvider::class, $sms);
        [$organization] = $this->organizationWithOwner();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi']);
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.templates.message'), ['event' => 'token_called', 'text' => 'Custom: {name} go in'])->assertSessionHas('success');
        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'x']);
        $this->assertSame('Custom: Ravi go in', end($sms->sent)[1]);

        $this->post(route('admin.templates.message'), ['event' => 'token_called', 'text' => ''])->assertSessionHas('success');
        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'x']);
        $this->assertStringContainsString('token 1', end($sms->sent)[1]);
    }

    public function test_emr_templates_added_by_admin_reach_doctors(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.templates.emr'), ['specialty' => 'Cardiology', 'name' => 'Chest pain', 'complaints' => 'Pain'])->assertSessionHas('success');

        $this->assertSame(1, Template::withoutTenancy()->whereNull('organization_id')->where('kind', 'emr')->count());
    }

    public function test_reports_count_recurring_revenue_for_active_subscriptions_only(): void
    {
        Mail::fake();
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        foreach (['active', 'active', 'trial'] as $status) {
            OrganizationSubscription::withoutTenancy()->create(['organization_id' => Organization::factory()->create()->id, 'plan_id' => $plan->id, 'status' => $status]);
        }

        $response = $this->actingAs($this->superAdmin(), 'platform')->get(route('admin.reports.index'))->assertOk();

        $this->assertSame(99800, $response->viewData('mrr'));
    }
}
