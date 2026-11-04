<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Refund;
use App\Models\SecurityEvent;
use App\Models\WebhookEvent;
use App\Services\Billing\BillingService;
use App\Services\Payments\CashfreeGateway;
use App\Services\Payments\GatewayFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CashfreeWebhookController extends Controller
{
    public function __construct(private readonly GatewayFactory $gateways, private readonly BillingService $billing) {}

    public function handle(Request $request, string $organizationUuid): Response
    {
        $organization = Organization::query()->where('uuid', $organizationUuid)->firstOrFail();
        $gateway = $this->gateways->forWebhook($organization->id, 'cashfree');
        $payload = $request->getContent();
        $signed = (string) $request->header('x-webhook-timestamp').$payload;

        // Nothing in the body is believed until this signature checks out.
        if ($gateway === null || ! $gateway->verifyWebhook($signed, (string) $request->header('x-webhook-signature'))) {
            SecurityEvent::record('webhook_rejected', null, null, $organization->id);

            return response('Invalid signature', 401);
        }

        $body = (array) json_decode($payload, true);
        $type = (string) ($body['type'] ?? '');

        try {
            $log = WebhookEvent::withoutTenancy()->create([
                'organization_id' => $organization->id,
                'provider' => 'cashfree',
                'event_id' => hash('sha256', $payload),
                'event_type' => $type,
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Cashfree retries deliveries. A repeat gets a 200 so that it stops, and does nothing.
            return response('Already processed');
        }

        if ($type === 'PAYMENT_LINK_EVENT' && data_get($body, 'data.link_status') === 'PAID' && data_get($body, 'data.order.transaction_status') === 'SUCCESS') {
            $this->billing->captureGatewayPayment($organization->id, (string) data_get($body, 'data.link_id'), (string) data_get($body, 'data.order.order_id'));
        }

        if (str_starts_with($type, 'REFUND') && filled(data_get($body, 'data.refund.refund_id'))) {
            Refund::withoutTenancy()->where('organization_id', $organization->id)
                ->where('gateway_refund_id', (string) data_get($body, 'data.refund.refund_id'))
                ->update(['status' => CashfreeGateway::refundStatus((string) data_get($body, 'data.refund.refund_status'))]);
        }

        $log->forceFill(['status' => 'processed'])->save();

        return response('OK');
    }

    /**
     * Where the patient lands after paying. Anyone could open this address, so nothing in
     * it is trusted: the answer comes from asking Cashfree, with this practice's keys.
     */
    public function returned(string $organizationUuid, string $linkId): View
    {
        $organization = Organization::query()->where('uuid', $organizationUuid)->firstOrFail();
        $gateway = $this->gateways->forWebhook($organization->id, 'cashfree');
        $orderId = null;

        if ($gateway instanceof CashfreeGateway) {
            try {
                $orderId = $gateway->paidOrderId($linkId);
            } catch (ConnectionException) {
                $orderId = null;
            }
        }

        if ($orderId !== null) {
            $this->billing->captureGatewayPayment($organization->id, $linkId, $orderId);
        }

        return view('public.payment-result', ['organization' => $organization, 'paid' => $orderId !== null]);
    }
}
