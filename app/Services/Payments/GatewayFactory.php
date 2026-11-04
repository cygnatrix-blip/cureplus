<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\Billing\FeatureGate;

class GatewayFactory
{
    /** A practice uses one at a time. */
    public const KINDS = ['razorpay', 'cashfree'];

    /**
     * Null when the organization has not switched online payments on.
     */
    public function forOrganization(int $organizationId): ?PaymentGateway
    {
        if (! Organization::query()->find($organizationId)?->isActive() || ! app(FeatureGate::class)->enabled($organizationId, 'online_payments')) {
            return null;
        }

        $integration = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->whereIn('kind', self::KINDS)
            ->where('is_enabled', true)
            ->first();

        return $integration ? $this->make($integration) : null;
    }

    /**
     * Webhooks and refunds must still work after a switch-off or a change of provider,
     * so this ignores is_enabled and is asked for one provider by name.
     */
    public function forWebhook(int $organizationId, string $kind = 'razorpay'): ?PaymentGateway
    {
        $integration = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', $kind)
            ->first();

        return $integration ? $this->make($integration) : null;
    }

    public function make(OrganizationIntegration $integration): PaymentGateway
    {
        if ($integration->kind === 'cashfree') {
            return new CashfreeGateway(
                (string) ($integration->config['key_id'] ?? ''),
                (string) ($integration->config['key_secret'] ?? ''),
                (string) ($integration->config['mode'] ?? 'test'),
            );
        }

        return new RazorpayGateway(
            (string) ($integration->config['key_id'] ?? ''),
            (string) ($integration->config['key_secret'] ?? ''),
            (string) ($integration->config['webhook_secret'] ?? ''),
        );
    }
}
