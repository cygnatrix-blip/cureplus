<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cashfree Payment Links. Cashfree's "payment id" for us is the order id, because that
 * is what its refund API takes. Unlike Razorpay, its return to our site carries no signature,
 * so a return is never believed: the status is asked for again with the practice's own keys.
 */
class CashfreeGateway implements PaymentGateway
{
    private const API_VERSION = '2026-01-01';

    public function __construct(
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly string $mode,
    ) {}

    public function provider(): string
    {
        return 'cashfree';
    }

    public function supportsReconciliation(): bool
    {
        return false;
    }

    /**
     * Asks for a link that does not exist: "not found" means Cashfree accepted the keys.
     */
    public function testConnection(): bool
    {
        return in_array($this->client()->get('/links/cureplus-connection-test')->status(), [200, 404], true);
    }

    public function createPaymentLink(array $link): array
    {
        $response = $this->client()->post('/links', [
            'link_id' => $link['reference'],
            'link_amount' => $link['amount_paise'] / 100,
            'link_currency' => 'INR',
            'link_purpose' => $link['description'],
            'customer_details' => array_filter([
                'customer_phone' => preg_replace('/^\+91/', '', $link['mobile']),
                'customer_name' => $link['name'],
                'customer_email' => $link['email'] ?? null,
            ]),
            // Cashfree only accepts https here, so a local machine leaves them out.
            'link_meta' => array_filter([
                'return_url' => $this->httpsOnly($link['callback_url']),
                'notify_url' => $this->httpsOnly($link['notify_url'] ?? null),
            ]),
            // We tell the patient ourselves, so they do not hear twice.
            'link_notify' => ['send_sms' => false, 'send_email' => false],
        ])->throw();

        return ['id' => (string) ($response->json('link_id') ?: $link['reference']), 'url' => (string) $response->json('link_url')];
    }

    public function refund(string $paymentId, int $amountPaise): array
    {
        $refundId = 'ref-'.Str::lower(Str::random(24));

        $response = $this->client()
            ->withHeaders(['x-idempotency-key' => (string) Str::uuid()])
            ->post('/orders/'.rawurlencode($paymentId).'/refunds', [
                'refund_id' => $refundId,
                'refund_amount' => $amountPaise / 100,
            ])->throw();

        return ['id' => $refundId, 'status' => self::refundStatus((string) $response->json('refund_status'))];
    }

    /**
     * Cashfree has no simple list of payments like Razorpay's, so its reconciliation
     * report is not offered. Practices compare against their Cashfree dashboard.
     */
    public function listPayments(int $from, int $to): array
    {
        return [];
    }

    public function verifyCallback(string $linkId, string $reference, string $status, string $paymentId, string $signature): bool
    {
        return false;
    }

    /**
     * The caller joins Cashfree's timestamp header and the raw body into $payload.
     * Cashfree signs with a base64 HMAC-SHA256 of that, using the practice's secret key.
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        return $this->clientSecret !== '' && $signature !== ''
            && hash_equals(base64_encode(hash_hmac('sha256', $payload, $this->clientSecret, true)), $signature);
    }

    /**
     * Whether this link is fully paid, and with which order. Null means "not confirmed",
     * including any reply we do not understand: the webhook can still complete it later.
     */
    public function paidOrderId(string $linkId): ?string
    {
        $link = $this->client()->get('/links/'.rawurlencode($linkId));

        if (! $link->successful() || $link->json('link_status') !== 'PAID') {
            return null;
        }

        $orders = $this->client()->get('/links/'.rawurlencode($linkId).'/orders');

        if (! $orders->successful()) {
            return null;
        }

        $rows = $orders->json('orders') ?? $orders->json();

        foreach (is_array($rows) ? $rows : [] as $order) {
            if (is_array($order) && ($order['order_status'] ?? null) === 'PAID' && filled($order['order_id'] ?? null)) {
                return (string) $order['order_id'];
            }
        }

        return null;
    }

    public static function refundStatus(string $status): string
    {
        return match ($status) {
            'SUCCESS' => 'processed',
            'CANCELLED' => 'failed',
            default => 'pending',
        };
    }

    private function httpsOnly(?string $url): ?string
    {
        return $url !== null && str_starts_with($url, 'https://') ? $url : null;
    }

    private function client(): PendingRequest
    {
        $host = $this->mode === 'live' ? 'https://api.cashfree.com/pg' : 'https://sandbox.cashfree.com/pg';

        return Http::baseUrl($host)
            ->withHeaders(['x-client-id' => $this->clientId, 'x-client-secret' => $this->clientSecret, 'x-api-version' => self::API_VERSION])
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
