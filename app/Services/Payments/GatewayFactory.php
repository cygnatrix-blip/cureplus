<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\OrganizationIntegration;
use App\Services\Billing\FeatureGate;

class GatewayFactory
{
    /**
     * Null when the organization has not switched online payments on.
     */
    public function forOrganization(int $organizationId): ?PaymentGateway
    {
        if (! app(FeatureGate::class)->enabled($organizationId, 'online_payments')) {
            return null;
        }

        $integration = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', 'razorpay')
            ->where('is_enabled', true)
            ->first();

        return $integration ? $this->make($integration) : null;
    }

    /**
     * Webhooks must still verify after a temporary switch-off, so this ignores is_enabled.
     */
    public function forWebhook(int $organizationId): ?PaymentGateway
    {
        $integration = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', 'razorpay')
            ->first();

        return $integration ? $this->make($integration) : null;
    }

    public function make(OrganizationIntegration $integration): PaymentGateway
    {
        return new RazorpayGateway(
            (string) ($integration->config['key_id'] ?? ''),
            (string) ($integration->config['key_secret'] ?? ''),
            (string) ($integration->config['webhook_secret'] ?? ''),
        );
    }
}
