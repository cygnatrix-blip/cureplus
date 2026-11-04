<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\WebhookEvent;
use App\Services\Billing\PlatformGateway;
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformWebhookController extends Controller
{
    public function __invoke(Request $request, PlatformGateway $gateway, SubscriptionService $subscriptions): Response
    {
        $payload = $request->getContent();

        if (! $gateway->verifyWebhook($payload, (string) $request->header('X-Razorpay-Signature'))) {
            SecurityEvent::record('webhook_rejected', 'platform');

            return response('Invalid signature', 401);
        }

        $body = (array) json_decode($payload, true);
        $event = (string) ($body['event'] ?? '');

        try {
            $log = WebhookEvent::withoutTenancy()->create([
                'provider' => 'razorpay_platform',
                'event_id' => (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $payload)),
                'event_type' => $event,
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('Already processed');
        }

        $subscriptionId = (string) data_get($body, 'payload.subscription.entity.id');

        if ($subscriptionId !== '') {
            match ($event) {
                'subscription.activated', 'subscription.charged', 'subscription.resumed' => $subscriptions->recordCharge(
                    $subscriptionId,
                    data_get($body, 'payload.payment.entity.id'),
                    (int) data_get($body, 'payload.payment.entity.amount', 0),
                    data_get($body, 'payload.subscription.entity.current_end'),
                ),
                'subscription.pending', 'subscription.halted' => $subscriptions->markPaymentFailed($subscriptionId),
                'subscription.cancelled', 'subscription.completed' => $subscriptions->markCancelled($subscriptionId),
                default => null,
            };
        }

        $log->forceFill(['status' => 'processed'])->save();

        return response('OK');
    }
}
