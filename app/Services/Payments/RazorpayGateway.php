<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class RazorpayGateway implements PaymentGateway
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function __construct(
        private readonly string $keyId,
        #[\SensitiveParameter] private readonly string $keySecret,
        #[\SensitiveParameter] private readonly string $webhookSecret,
    ) {}

    public function testConnection(): bool
    {
        return $this->client()->get('/payments', ['count' => 1])->successful();
    }

    public function createPaymentLink(array $link): array
    {
        $response = $this->client()->post('/payment_links', [
            'amount' => $link['amount_paise'],
            'currency' => 'INR',
            'reference_id' => $link['reference'],
            'description' => $link['description'],
            'customer' => ['name' => $link['name'], 'contact' => $link['mobile']],
            'callback_url' => $link['callback_url'],
            'callback_method' => 'get',
        ])->throw();

        return ['id' => (string) $response->json('id'), 'url' => (string) $response->json('short_url')];
    }

    public function refund(string $paymentId, int $amountPaise): array
    {
        $response = $this->client()->post("/payments/{$paymentId}/refund", ['amount' => $amountPaise])->throw();

        return ['id' => (string) $response->json('id'), 'status' => (string) $response->json('status')];
    }

    public function listPayments(int $from, int $to): array
    {
        $payments = [];
        $skip = 0;

        do {
            $items = (array) $this->client()->get('/payments', ['from' => $from, 'to' => $to, 'count' => 100, 'skip' => $skip])->throw()->json('items', []);

            foreach ($items as $item) {
                $payments[] = ['id' => (string) $item['id'], 'amount_paise' => (int) $item['amount'], 'status' => (string) $item['status'], 'created_at' => (int) $item['created_at']];
            }

            $skip += 100;
        } while (count($items) === 100 && $skip < 5000);

        return $payments;
    }

    /**
     * hash_equals, not ===: a timing difference must not help an attacker forge a "paid" redirect.
     */
    public function verifyCallback(string $linkId, string $reference, string $status, string $paymentId, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', "{$linkId}|{$reference}|{$status}|{$paymentId}", $this->keySecret), $signature);
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        return $this->webhookSecret !== '' && hash_equals(hash_hmac('sha256', $payload, $this->webhookSecret), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withBasicAuth($this->keyId, $this->keySecret)->acceptJson()->asJson()->timeout(15);
    }
}
