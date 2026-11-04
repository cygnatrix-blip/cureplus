<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Refund;
use App\Models\SecurityEvent;
use App\Models\WebhookEvent;
use App\Services\Billing\BillingService;
use App\Services\Payments\GatewayFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RazorpayWebhookController extends Controller
{
    public function __construct(private readonly GatewayFactory $gateways, private readonly BillingService $billing) {}

    public function handle(Request $request, string $organizationUuid): Response
    {
        $organization = Organization::query()->where('uuid', $organizationUuid)->firstOrFail();
        $gateway = $this->gateways->forWebhook($organization->id);
        $payload = $request->getContent();

        // Nothing in the body is believed until this signature checks out.
        if ($gateway === null || ! $gateway->verifyWebhook($payload, (string) $request->header('X-Razorpay-Signature'))) {
            SecurityEvent::record('webhook_rejected', null, null, $organization->id);

            return response('Invalid signature', 401);
        }

        $body = (array) json_decode($payload, true);
        $event = (string) ($body['event'] ?? '');
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $payload));

        try {
            $log = WebhookEvent::withoutTenancy()->create([
                'organization_id' => $organization->id,
                'provider' => 'razorpay',
                'event_id' => $eventId,
                'event_type' => $event,
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Razorpay retries deliveries. A repeat gets a 200 so that it stops, and does nothing.
            return response('Already processed');
        }

        match (true) {
            $event === 'payment_link.paid' => $this->billing->captureGatewayPayment(
                $organization->id,
                (string) data_get($body, 'payload.payment_link.entity.id'),
                (string) data_get($body, 'payload.payment.entity.id'),
            ),
            str_starts_with($event, 'refund.') => $this->syncRefund($organization->id, $body),
            default => null,
        };

        $log->forceFill(['status' => 'processed'])->save();

        return response('OK');
    }

    /**
     * Where the patient lands after paying. The webhook is the source of truth, but it can
     * lag, so a correctly signed redirect is allowed to complete the payment as well.
     */
    public function callback(Request $request, string $organizationUuid): View|RedirectResponse
    {
        $organization = Organization::query()->where('uuid', $organizationUuid)->firstOrFail();
        $gateway = $this->gateways->forWebhook($organization->id);

        $linkId = (string) $request->query('razorpay_payment_link_id');
        $reference = (string) $request->query('razorpay_payment_link_reference_id');
        $status = (string) $request->query('razorpay_payment_link_status');
        $paymentId = (string) $request->query('razorpay_payment_id');

        $verified = $gateway !== null && $gateway->verifyCallback($linkId, $reference, $status, $paymentId, (string) $request->query('razorpay_signature'));

        if ($verified && $status === 'paid') {
            $this->billing->captureGatewayPayment($organization->id, $linkId, $paymentId);
        }

        return view('public.payment-result', ['organization' => $organization, 'paid' => $verified && $status === 'paid']);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function syncRefund(int $organizationId, array $body): void
    {
        $id = (string) data_get($body, 'payload.refund.entity.id');
        $status = (string) data_get($body, 'payload.refund.entity.status');

        if ($id !== '' && $status !== '') {
            Refund::withoutTenancy()->where('organization_id', $organizationId)->where('gateway_refund_id', $id)->update(['status' => $status]);
        }
    }
}
