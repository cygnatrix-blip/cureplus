<?php

namespace App\Services\Billing;

use App\Enums\OrganizationStatus;
use App\Exceptions\BillingException;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Services\AuditLogger;
use App\Services\OrganizationOwners;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    private const SUBSCRIPTION_SUSPENSION = 'Subscription not paid';

    public function __construct(private readonly PlatformGateway $gateway, private readonly AuditLogger $audit, private readonly OrganizationOwners $owners) {}

    public function startTrial(Organization $organization): ?OrganizationSubscription
    {
        $plan = Plan::query()->where('code', config('plans.default_plan'))->where('is_active', true)->first();

        if ($plan === null) {
            return null;
        }

        return OrganizationSubscription::withoutTenancy()->firstOrCreate(['organization_id' => $organization->id], [
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays($plan->trial_days),
        ]);
    }

    /**
     * Sends the owner to Razorpay's hosted page. No key reaches the browser, and the
     * subscription only becomes active when a signed webhook says it was paid.
     */
    public function checkout(Organization $organization, Plan $plan, ?string $couponCode = null): OrganizationSubscription
    {
        if (! $this->gateway->isConfigured() || blank($plan->razorpay_plan_id)) {
            throw new BillingException('Online subscription is not available for this plan yet. Please contact support.');
        }

        $coupon = $this->redeemableCoupon($couponCode);
        $remote = $this->gateway->createSubscription((string) $plan->razorpay_plan_id, $coupon?->razorpay_offer_id, $organization->id);

        $subscription = OrganizationSubscription::withoutTenancy()->firstOrNew(['organization_id' => $organization->id]);
        $subscription->status ??= 'pending';
        $subscription->fill([
            'plan_id' => $plan->id,
            'razorpay_subscription_id' => $remote['id'],
            'checkout_url' => $remote['url'],
            'coupon_id' => $coupon?->id,
        ])->save();

        return $subscription;
    }

    /**
     * Extends a trial straight away, for coupons that give extra days and need no payment.
     */
    public function applyTrialCoupon(Organization $organization, string $code): int
    {
        return DB::transaction(function () use ($organization, $code): int {
            $coupon = Coupon::query()->where('code', Str::upper(trim($code)))->lockForUpdate()->first();

            if ($coupon === null || ! $coupon->isRedeemable() || $coupon->extra_trial_days <= 0) {
                throw new BillingException('This coupon cannot be used.');
            }

            $subscription = OrganizationSubscription::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();

            if ($subscription->coupon_id !== null) {
                throw new BillingException('A coupon has already been used on this subscription.');
            }

            $subscription->fill([
                'trial_ends_at' => ($subscription->trial_ends_at ?? now())->copy()->addDays($coupon->extra_trial_days),
                'coupon_id' => $coupon->id,
            ])->save();
            $coupon->increment('redeemed_count');

            return (int) $coupon->extra_trial_days;
        });
    }

    public function cancel(Organization $organization): void
    {
        $subscription = OrganizationSubscription::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();

        if ($subscription->razorpay_subscription_id && $this->gateway->isConfigured()) {
            $this->gateway->cancelSubscription($subscription->razorpay_subscription_id);
        }

        $subscription->fill(['cancelled_at' => now()])->save();
        $this->audit->record('subscriptions.cancelled', $organization);
    }

    /**
     * Repeated webhooks are safe: the payment id is unique on the invoice.
     */
    public function recordCharge(string $razorpaySubscriptionId, ?string $paymentId, int $amountPaise, ?int $periodEnd): void
    {
        $subscription = OrganizationSubscription::withoutTenancy()->where('razorpay_subscription_id', $razorpaySubscriptionId)->with('plan')->first();

        if ($subscription === null) {
            return;
        }

        DB::transaction(function () use ($subscription, $paymentId, $amountPaise, $periodEnd): void {
            if ($paymentId !== null && $amountPaise > 0 && ! PlatformInvoice::withoutTenancy()->where('razorpay_payment_id', $paymentId)->exists()) {
                PlatformInvoice::withoutTenancy()->create([
                    'organization_id' => $subscription->organization_id,
                    'plan_id' => $subscription->plan_id,
                    'number' => 'SUB-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
                    'amount_paise' => $amountPaise,
                    'status' => 'paid',
                    'razorpay_payment_id' => $paymentId,
                    'paid_at' => now(),
                ]);
            }

            $subscription->fill([
                'status' => 'active',
                'grace_ends_at' => null,
                'current_period_ends_at' => $periodEnd ? now()->setTimestamp($periodEnd) : now()->addMonth(),
            ])->save();

            $this->reinstate((int) $subscription->organization_id);
        });
    }

    public function markPaymentFailed(string $razorpaySubscriptionId): void
    {
        $subscription = OrganizationSubscription::withoutTenancy()->where('razorpay_subscription_id', $razorpaySubscriptionId)->first();

        if ($subscription !== null && $subscription->status !== 'past_due') {
            $subscription->fill(['status' => 'past_due', 'grace_ends_at' => now()->addDays((int) config('plans.grace_days'))])->save();
            $this->owners->email((int) $subscription->organization_id, 'Your subscription payment failed', 'We could not take your subscription payment. Please update your payment details to keep your account active.');
        }
    }

    public function markCancelled(string $razorpaySubscriptionId): void
    {
        OrganizationSubscription::withoutTenancy()->where('razorpay_subscription_id', $razorpaySubscriptionId)
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    /**
     * Daily. A trial or a paid period that ran out gets a grace period; after that the
     * organization is suspended. Its records are untouched, and paying restores access.
     *
     * @return array{graced: int, suspended: int}
     */
    public function enforce(): array
    {
        $graced = 0;
        $suspended = 0;

        $expiredTrials = OrganizationSubscription::withoutTenancy()->where('status', 'trial')->where('trial_ends_at', '<', now())->get();

        foreach ($expiredTrials as $subscription) {
            $subscription->fill(['status' => 'past_due', 'grace_ends_at' => now()->addDays((int) config('plans.grace_days'))])->save();
            $this->owners->email((int) $subscription->organization_id, 'Your free trial has ended', 'Please choose a plan to keep using your account.');
            $graced++;
        }

        $overdue = OrganizationSubscription::withoutTenancy()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('status', 'past_due')->where('grace_ends_at', '<', now()))
                ->orWhere(fn ($query) => $query->where('status', 'cancelled')->where('current_period_ends_at', '<', now())))
            ->get();

        foreach ($overdue as $subscription) {
            $organization = Organization::query()->find($subscription->organization_id);

            if ($organization?->status === OrganizationStatus::Active) {
                $organization->forceFill(['status' => OrganizationStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => self::SUBSCRIPTION_SUSPENSION])->save();
                $this->owners->email($organization->id, 'Your account has been suspended', 'Your subscription was not paid. Your records are safe. Sign in and renew to restore access.');
                $suspended++;
            }
        }

        return ['graced' => $graced, 'suspended' => $suspended];
    }

    /**
     * Paying only lifts a suspension caused by non-payment. If an admin suspended
     * the organization for another reason, a payment must not undo that.
     */
    private function reinstate(int $organizationId): void
    {
        $organization = Organization::query()->find($organizationId);

        if ($organization?->status === OrganizationStatus::Suspended && $organization->suspension_reason === self::SUBSCRIPTION_SUSPENSION) {
            $organization->forceFill(['status' => OrganizationStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])->save();
        }
    }

    private function redeemableCoupon(?string $code): ?Coupon
    {
        if (blank($code)) {
            return null;
        }

        $coupon = Coupon::query()->where('code', Str::upper(trim((string) $code)))->first();

        if ($coupon === null || ! $coupon->isRedeemable()) {
            throw new BillingException('This coupon cannot be used.');
        }

        return $coupon;
    }
}
