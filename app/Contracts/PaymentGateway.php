<?php

namespace App\Contracts;

/**
 * Per-organization payment provider. Razorpay Route (linked accounts) or another
 * gateway can be added behind this without touching billing code.
 */
interface PaymentGateway
{
    public function testConnection(): bool;

    /**
     * @param  array{amount_paise: int, reference: string, description: string, name: string, mobile: string, callback_url: string}  $link
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
