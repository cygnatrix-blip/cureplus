<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The platform's own Razorpay account, for what organizations pay the platform.
 * Entirely separate from each organization's account for patient payments.
 */
class PlatformGateway
{
    public function isConfigured(): bool
    {
        return ! blank(config('plans.razorpay.key_id')) && ! blank(config('plans.razorpay.key_secret'));
    }

    /**
     * @return array{id: string, url: string}
     */
    public function createSubscription(string $razorpayPlanId, ?string $offerId, int $organizationId): array
    {
        $response = $this->client()->post('/subscriptions', array_filter([
            'plan_id' => $razorpayPlanId,
            'total_count' => config('plans.razorpay.subscription_cycles'),
            'customer_notify' => 1,
            'offer_id' => $offerId,
            'notes' => ['organization_id' => (string) $organizationId],
        ]))->throw();

        return ['id' => (string) $response->json('id'), 'url' => (string) $response->json('short_url')];
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->client()->post("/subscriptions/{$subscriptionId}/cancel", ['cancel_at_cycle_end' => 1])->throw();
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = (string) config('plans.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.razorpay.com/v1')
            ->withBasicAuth((string) config('plans.razorpay.key_id'), (string) config('plans.razorpay.key_secret'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
