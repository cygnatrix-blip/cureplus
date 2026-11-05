<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Enums\OrganizationStatus;
use App\Models\Coupon;
use App\Models\FeatureFlag;
use App\Models\Location;
use App\Models\MessageLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Billing\FeatureGate;
use App\Services\Billing\SubscriptionService;
use App\Services\Messaging\Notifier;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeSms;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    private const SECRET = 'platform_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['plans.enabled' => true]);
        Mail::fake();
        $this->seed(PlanSeeder::class);
        config(['plans.razorpay' => ['key_id' => 'rzp_plat', 'key_secret' => 'plat_secret', 'webhook_secret' => self::SECRET, 'subscription_cycles' => 12]]);
    }

    /**
     * @return array{Organization, User, OrganizationSubscription}
     */
    private function subscribed(string $status = 'trial'): array
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $subscription = OrganizationSubscription::withoutTenancy()->create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::query()->where('code', 'starter')->value('id'),
            'status' => $status,
            'trial_ends_at' => now()->addDays(14),
            'razorpay_subscription_id' => 'sub_'.$organization->id,
        ]);

        return [$organization, $owner, $subscription];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function webhook(array $body, string $eventId = 'evt_1', ?string $signature = null): TestResponse
    {
        $payload = json_encode($body);

        return $this->call('POST', route('webhooks.razorpay.platform'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $payload, self::SECRET),
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function charged(string $subscriptionId, string $paymentId = 'pay_1', string $event = 'subscription.charged'): array
    {
        return ['event' => $event, 'payload' => [
            'subscription' => ['entity' => ['id' => $subscriptionId, 'current_end' => now()->addMonth()->timestamp]],
            'payment' => ['entity' => ['id' => $paymentId, 'amount' => 49900]],
        ]];
    }

    public function test_a_new_registration_starts_on_a_trial_when_approval_is_not_required(): void
    {
        Notification::fake();
        config(['cureplus.organizations.require_approval' => false]);

        $this->post('/register', [
            'type' => 'solo_practice', 'name' => 'Rohan Roy', 'email' => 'r@example.test', 'mobile' => '9812345678',
            'address_line' => '12 Park Road', 'city' => 'Kolkata', 'registration_number' => 'WB-1', 'specialty' => 'General Physician', 'registration_council' => 'West Bengal Medical Council', 'qualification' => 'MBBS',
            'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1',
        ]);

        $subscription = OrganizationSubscription::withoutTenancy()->firstOrFail();
        $this->assertSame('trial', $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));
    }

    public function test_an_expired_trial_gets_grace_then_suspension_and_records_stay(): void
    {
        [$organization, , $subscription] = $this->subscribed();
        $service = app(SubscriptionService::class);

        $this->travel(15)->days();
        $this->assertSame(['graced' => 1, 'suspended' => 0], $service->enforce());
        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertSame(OrganizationStatus::Approved, $organization->fresh()->status);

        $this->travel(config('plans.grace_days') + 1)->days();
        $this->assertSame(['graced' => 0, 'suspended' => 1], $service->enforce());
        $this->assertSame(OrganizationStatus::Suspended, $organization->fresh()->status);
        $this->assertNotNull(Location::withoutTenancy()->where('organization_id', $organization->id)->first());
    }

    public function test_a_charge_activates_and_a_repeat_makes_one_invoice(): void
    {
        [, , $subscription] = $this->subscribed();

        $this->webhook($this->charged($subscription->razorpay_subscription_id))->assertOk();
        $this->webhook($this->charged($subscription->razorpay_subscription_id))->assertOk();
        $this->webhook($this->charged($subscription->razorpay_subscription_id), 'evt_2')->assertOk();

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(1, PlatformInvoice::withoutTenancy()->count());
        $this->assertSame(49900, PlatformInvoice::withoutTenancy()->firstOrFail()->amount_paise);
    }

    public function test_a_forged_platform_webhook_is_refused_and_logged(): void
    {
        [, , $subscription] = $this->subscribed();

        $this->webhook($this->charged($subscription->razorpay_subscription_id), 'evt_1', 'forged')->assertStatus(401);

        $this->assertSame('trial', $subscription->fresh()->status);
        $this->assertSame(1, SecurityEvent::query()->where('type', 'webhook_rejected')->count());
    }

    public function test_paying_lifts_a_non_payment_suspension_only(): void
    {
        [$unpaid, , $unpaidSubscription] = $this->subscribed();
        [$banned, , $bannedSubscription] = $this->subscribed();
        $unpaid->forceFill(['status' => OrganizationStatus::Suspended, 'suspension_reason' => 'Subscription not paid'])->save();
        $banned->forceFill(['status' => OrganizationStatus::Suspended, 'suspension_reason' => 'Fraud'])->save();

        $this->webhook($this->charged($unpaidSubscription->razorpay_subscription_id, 'pay_a'), 'evt_a');
        $this->webhook($this->charged($bannedSubscription->razorpay_subscription_id, 'pay_b'), 'evt_b');

        $this->assertSame(OrganizationStatus::Approved, $unpaid->fresh()->status);
        $this->assertSame(OrganizationStatus::Suspended, $banned->fresh()->status);
    }

    public function test_a_failed_payment_starts_the_grace_period(): void
    {
        [, , $subscription] = $this->subscribed('active');

        $this->webhook(['event' => 'subscription.halted', 'payload' => ['subscription' => ['entity' => ['id' => $subscription->razorpay_subscription_id]]]])->assertOk();

        $fresh = $subscription->fresh();
        $this->assertSame('past_due', $fresh->status);
        $this->assertTrue($fresh->grace_ends_at->isFuture());
    }

    public function test_checkout_goes_to_razorpay_and_activates_nothing_on_its_own(): void
    {
        Http::fake(['api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_new', 'short_url' => 'https://rzp.io/sub/x'])]);
        [, $owner, $subscription] = $this->subscribed();
        $plan = Plan::query()->where('code', 'clinic')->firstOrFail();
        $plan->update(['razorpay_plan_id' => 'plan_123']);

        $this->actingAs($owner, 'web')->post(route('app.subscription.checkout'), ['plan_id' => $plan->id])
            ->assertOk()->assertSee('https://rzp.io/sub/x')->assertDontSee('plat_secret');

        Http::assertSent(fn (HttpRequest $request) => $request['plan_id'] === 'plan_123');
        $fresh = $subscription->fresh();
        $this->assertSame('sub_new', $fresh->razorpay_subscription_id);
        $this->assertSame('trial', $fresh->status);
    }

    public function test_a_plan_without_a_razorpay_id_gives_a_clear_message(): void
    {
        [, $owner] = $this->subscribed();
        $plan = Plan::query()->where('code', 'clinic')->firstOrFail();

        $this->actingAs($owner, 'web')->from(route('app.subscription.index'))->post(route('app.subscription.checkout'), ['plan_id' => $plan->id])
            ->assertRedirect(route('app.subscription.index'))->assertSessionHas('error');
    }

    public function test_a_suspended_owner_can_still_renew_but_other_staff_cannot(): void
    {
        Http::fake(['api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_r', 'short_url' => 'https://rzp.io/sub/r'])]);
        [$organization, $owner] = $this->subscribed();
        $nurse = $this->addMember($organization, ['nurse']);
        $organization->forceFill(['status' => OrganizationStatus::Suspended, 'suspension_reason' => 'Subscription not paid'])->save();
        $plan = Plan::query()->where('code', 'clinic')->firstOrFail();
        $plan->update(['razorpay_plan_id' => 'plan_123']);
        $session = ['active_organization_id' => $organization->id];

        $this->actingAs($owner, 'web')->withSession($session)->get(route('app.dashboard'))->assertRedirect(route('app.organization.status'));
        $this->get(route('app.organization.status'))->assertOk()->assertSee('Renew');
        $this->post(route('app.subscription.renew'), ['plan_id' => $plan->id])->assertOk()->assertSee('https://rzp.io/sub/r');

        $this->actingAs($nurse, 'web')->withSession($session)->post(route('app.subscription.renew'), ['plan_id' => $plan->id])->assertNotFound();
    }

    public function test_plan_limits_stop_new_locations_and_invitations(): void
    {
        [$organization, $owner] = $this->subscribed();
        $this->actingAs($owner, 'web');

        // Starter allows two locations, and this organization has one.
        $this->post(route('app.locations.store'), ['name' => 'Second', 'is_active' => 1])->assertSessionHas('success');
        $this->post(route('app.locations.store'), ['name' => 'Third', 'is_active' => 1])->assertSessionHas('error');
        $this->assertSame(2, Location::withoutTenancy()->where('organization_id', $organization->id)->count());

        // Starter allows three team members, and the owner is one.
        foreach (range(1, 2) as $ignored) {
            $this->addMember($organization, ['nurse']);
        }
        $location = Location::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();
        $this->post(route('app.invitations.store'), ['email' => 'x@example.test', 'role_keys' => ['nurse'], 'location_ids' => [$location->id]])->assertSessionHas('error');
    }

    public function test_paid_messages_stop_at_the_monthly_limit(): void
    {
        $sms = new FakeSms;
        $this->app->instance(SmsProvider::class, $sms);
        [$organization] = $this->subscribed();
        Plan::query()->where('code', 'starter')->firstOrFail()->update(['limits' => ['messages_per_month' => 1]]);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $notifier = app(Notifier::class);

        $notifier->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'x']);
        $notifier->notifyPatient($patient, 'token_called', ['token' => 2, 'doctor' => 'x']);

        $this->assertCount(1, $sms->sent);
        $this->assertSame('over_limit', MessageLog::withoutTenancy()->latest('id')->firstOrFail()->status);
    }

    public function test_features_follow_the_plan_and_flags_override_it(): void
    {
        [$organization, $owner, $subscription] = $this->subscribed();
        Plan::query()->whereKey($subscription->plan_id)->update(['features' => ['billing']]);
        $this->actingAs($owner, 'web');

        $this->get(route('app.invoices.index'))->assertOk();
        $this->get(route('app.reports.index'))->assertRedirect(route('app.subscription.index'));

        FeatureFlag::withoutTenancy()->create(['feature' => 'reports', 'organization_id' => $organization->id, 'is_enabled' => true]);
        app(FeatureGate::class)->forget();
        $this->get(route('app.reports.index'))->assertOk();

        FeatureFlag::withoutTenancy()->create(['feature' => 'billing', 'organization_id' => $organization->id, 'is_enabled' => false]);
        app(FeatureGate::class)->forget();
        $this->get(route('app.invoices.index'))->assertRedirect(route('app.subscription.index'));
    }

    public function test_online_booking_can_be_switched_off(): void
    {
        [$organization] = $this->subscribed();
        Plan::query()->where('code', 'starter')->update(['features' => []]);

        $this->get('/'.$organization->slug)->assertNotFound();
    }

    public function test_an_organization_with_no_plan_keeps_every_feature(): void
    {
        [$organization] = $this->organizationWithOwner();

        $this->assertTrue(app(FeatureGate::class)->enabled($organization->id, 'reports'));
    }

    public function test_a_coupon_adds_trial_days_once(): void
    {
        [, $owner, $subscription] = $this->subscribed();
        $before = $subscription->trial_ends_at;
        Coupon::query()->create(['code' => 'EXTRA7', 'extra_trial_days' => 7]);
        Coupon::query()->create(['code' => 'OLD', 'extra_trial_days' => 7, 'expires_at' => now()->subDay()]);
        $this->actingAs($owner, 'web');

        $this->post(route('app.subscription.coupon'), ['code' => 'OLD'])->assertSessionHas('error');
        $this->post(route('app.subscription.coupon'), ['code' => 'extra7'])->assertSessionHas('success');
        $this->post(route('app.subscription.coupon'), ['code' => 'extra7'])->assertSessionHas('error');

        $this->assertTrue($subscription->fresh()->trial_ends_at->equalTo($before->copy()->addDays(7)));
        $this->assertSame(1, Coupon::query()->where('code', 'EXTRA7')->value('redeemed_count'));
    }

    public function test_the_subscription_page_invoice_pdf_and_isolation(): void
    {
        [$organization, $owner] = $this->subscribed();
        [$other, $stranger] = $this->subscribed();
        $invoice = PlatformInvoice::withoutTenancy()->create(['organization_id' => $organization->id, 'number' => 'SUB-1', 'amount_paise' => 100, 'paid_at' => now()]);

        $this->actingAs($owner, 'web')->get(route('app.subscription.index'))->assertOk()->assertSee('SUB-1');
        $this->assertStringStartsWith('%PDF', $this->get(route('app.subscription.invoice', $invoice))->assertOk()->getContent());

        $this->actingAs($stranger, 'web')->get(route('app.subscription.invoice', $invoice->id))->assertNotFound();
        $this->assertNotNull($other);
    }

    public function test_a_receptionist_cannot_manage_the_subscription(): void
    {
        [$organization] = $this->subscribed();

        $this->actingAs($this->addMember($organization, ['receptionist']), 'web')->get(route('app.subscription.index'))->assertForbidden();
    }
}
