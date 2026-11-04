<?php

namespace App\Contracts;

/**
 * Per-organization payment provider. Razorpay and Cashfree sit behind this, and another
 * gateway can be added without touching billing code.
 */
interface PaymentGateway
{
    /**
     * The word stored as the payment's mode and the integration's kind.
     */
    public function provider(): string;

    public function supportsReconciliation(): bool;

    public function testConnection(): bool;

    /**
     * @param  array{amount_paise: int, reference: string, description: string, name: string, mobile: string, email?: string|null, callback_url: string, notify_url?: string|null}  $link
     * @return array{id: string, url: string}
     */
    public function createPaymentLink(array $link): array;

    /**
     * @return array{id: string, status: string}
     */
    public function refund(string $paymentId, int $amountPaise): array;

    /**
     * @return list<array{id: string, amount_paise: int, status: string, created_at: int}>
     */
    public function listPayments(int $from, int $to): array;

    public function verifyCallback(string $linkId, string $reference, string $status, string $paymentId, string $signature): bool;

    public function verifyWebhook(string $payload, string $signature): bool;
}
