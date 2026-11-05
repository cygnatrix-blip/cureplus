<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Exceptions\BillingException;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\AuditLogger;
use App\Services\Messaging\Notifier;
use App\Services\Payments\GatewayFactory;
use App\Services\SequenceService;
use App\Services\VideoPayments;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly SequenceService $sequence,
        private readonly AuditLogger $audit,
        private readonly GatewayFactory $gateways,
        private readonly Notifier $notifier,
        private readonly VideoPayments $videoPayments,
    ) {}

    /**
     * @param  list<array{service_item_id?: int|null, description: string, sac_hsn_code?: string|null, quantity: int, unit_paise: int, is_taxable: bool, gst_rate_bp: int}>  $lines
     * @param  array{location_id: int, doctor_user_id?: int|null, appointment_id?: int|null, discount_paise?: int, discount_reason?: string|null}  $context
     */
    public function createInvoice(Organization $organization, Patient $patient, array $lines, array $context): Invoice
    {
        $totals = $this->calculator->calculate($lines, (int) ($context['discount_paise'] ?? 0));

        // Tax rules decide the document: a GST invoice only when something taxable is on it.
        $isGst = $totals['tax_paise'] > 0 || $totals['taxable_paise'] > 0;

        return DB::transaction(function () use ($organization, $patient, $lines, $context, $totals, $isGst): Invoice {
            $invoice = Invoice::withoutTenancy()->create([
                'organization_id' => $organization->id,
                'location_id' => $context['location_id'],
                'patient_id' => $patient->id,
                'doctor_user_id' => $context['doctor_user_id'] ?? null,
                'appointment_id' => $context['appointment_id'] ?? null,
                'number' => $this->nextNumber($organization->id, $context['location_id'], $isGst),
                'kind' => $isGst ? 'gst_invoice' : 'receipt',
                'status' => $totals['total_paise'] === 0 ? 'paid' : 'unpaid',
                'subtotal_paise' => $totals['subtotal_paise'],
                'discount_paise' => $totals['discount_paise'],
                'discount_reason' => $context['discount_reason'] ?? null,
                'exempt_paise' => $totals['exempt_paise'],
                'taxable_paise' => $totals['taxable_paise'],
                'tax_paise' => $totals['tax_paise'],
                'total_paise' => $totals['total_paise'],
                'gstin_snapshot' => $isGst ? $organization->gstin : null,
                'issued_at' => now(),
                'created_by' => Auth::guard('web')->id(),
            ]);

            foreach ($totals['lines'] as $index => $line) {
                $invoice->items()->create($line + [
                    'organization_id' => $organization->id,
                    'service_item_id' => $lines[$index]['service_item_id'] ?? null,
                    'description' => $lines[$index]['description'],
                    'sac_hsn_code' => $lines[$index]['sac_hsn_code'] ?? null,
                ]);
            }

            if ($totals['discount_paise'] > 0) {
                $this->audit->record('invoices.discounted', $invoice, ['discount_paise' => $totals['discount_paise'], 'reason' => $context['discount_reason'] ?? null]);
            }

            return $invoice;
        });
    }

    public function recordPayment(Invoice $invoice, int $amountPaise, string $mode, ?string $reference = null): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $amountPaise, $mode, $reference): Payment {
            $locked = $this->lock($invoice);

            if ($locked->status === 'cancelled') {
                throw new BillingException('This bill has been cancelled.');
            }

            if ($amountPaise <= 0 || $amountPaise > $locked->duePaise()) {
                throw new BillingException('The amount must be more than zero and no more than the amount due.');
            }

            $payment = Payment::withoutTenancy()->create([
                'organization_id' => $locked->organization_id,
                'invoice_id' => $locked->id,
                'amount_paise' => $amountPaise,
                'mode' => $mode,
                'status' => 'captured',
                'reference' => $reference,
                'paid_at' => now(),
                'recorded_by' => Auth::guard('web')->id(),
            ]);

            $this->applyPaid($locked, $amountPaise);
            $invoice->setRawAttributes($locked->getAttributes());

            return $payment;
        });

        $this->tellPatient($invoice, $payment);
        $this->videoPayments->settled($invoice);

        return $payment;
    }

    /**
     * The one place a gateway payment is credited, used by webhook and callback alike.
     * Whichever arrives second finds the payment already captured and changes nothing.
     */
    public function captureGatewayPayment(int $organizationId, string $linkId, string $gatewayPaymentId): bool
    {
        $captured = null;

        $changed = DB::transaction(function () use ($organizationId, $linkId, $gatewayPaymentId, &$captured): bool {
            $payment = Payment::withoutTenancy()
                ->where('organization_id', $organizationId)
                ->where('gateway_link_id', $linkId)
                ->lockForUpdate()
                ->first();

            if ($payment === null || $payment->status === 'captured') {
                return false;
            }

            $invoice = $this->lock(Invoice::withoutTenancy()->findOrFail($payment->invoice_id));

            $payment->forceFill(['status' => 'captured', 'gateway_payment_id' => $gatewayPaymentId, 'paid_at' => now()])->save();
            $this->applyPaid($invoice, $payment->amount_paise);
            $captured = [$invoice, $payment];

            return true;
        });

        if ($captured !== null) {
            $this->tellPatient($captured[0], $captured[1]);
            $this->videoPayments->settled($captured[0]->refresh());
        }

        return $changed;
    }

    public function createPaymentLink(Invoice $invoice, PaymentGateway $gateway): Payment
    {
        // Explicit, because the patient portal calls this with no tenant set.
        $patient = Patient::withoutTenancy()->findOrFail($invoice->patient_id);
        $due = $invoice->duePaise();

        if ($due <= 0) {
            throw new BillingException('Nothing is due on this bill.');
        }

        $organization = Organization::query()->findOrFail($invoice->organization_id);

        $reference = "inv-{$invoice->id}-".now()->timestamp;

        $link = $gateway->createPaymentLink([
            'amount_paise' => $due,
            'reference' => $reference,
            'description' => "Bill {$invoice->number}",
            'name' => $patient->name,
            'mobile' => $patient->mobile,
            'email' => $patient->email,
            'callback_url' => $gateway->provider() === 'cashfree' ? route('pay.return.cashfree', [$organization->uuid, $reference]) : route('pay.callback', $organization->uuid),
            'notify_url' => $gateway->provider() === 'cashfree' ? route('webhooks.cashfree', $organization->uuid) : null,
        ]);

        // An older unpaid link must not stay live alongside the new amount.
        Payment::withoutTenancy()->where('invoice_id', $invoice->id)->where('status', 'pending')->update(['status' => 'superseded']);

        return Payment::withoutTenancy()->create([
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'amount_paise' => $due,
            'mode' => $gateway->provider(),
            'status' => 'pending',
            'gateway_link_id' => $link['id'],
            'gateway_link_url' => $link['url'],
            'recorded_by' => Auth::guard('web')->id(),
        ]);
    }

    public function refund(Payment $payment, int $amountPaise, string $reason): Refund
    {
        $gatewayRefund = null;

        // Money moves at the gateway first. If that call fails nothing is recorded, and if
        // it succeeds the local record below is what reconciliation will match it against.
        if (in_array($payment->mode, Payment::ONLINE_MODES, true)) {
            if ($payment->gateway_payment_id === null) {
                throw new BillingException('This online payment has not completed.');
            }

            $this->assertRefundable($payment, $amountPaise);
            $gateway = $this->gateways->forWebhook((int) $payment->organization_id, $payment->mode)
                ?? throw new BillingException('This payment provider is not set up for this organization.');
            $gatewayRefund = $gateway->refund($payment->gateway_payment_id, $amountPaise);
        }

        return DB::transaction(function () use ($payment, $amountPaise, $reason, $gatewayRefund): Refund {
            $invoice = $this->lock(Invoice::withoutTenancy()->findOrFail($payment->invoice_id));
            $this->assertRefundable($payment, $amountPaise);

            $refund = Refund::withoutTenancy()->create([
                'organization_id' => $payment->organization_id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount_paise' => $amountPaise,
                'reason' => $reason,
                'status' => $gatewayRefund['status'] ?? 'processed',
                'gateway_refund_id' => $gatewayRefund['id'] ?? null,
                'refunded_at' => now(),
                'created_by' => Auth::guard('web')->id(),
            ]);

            $refunded = $invoice->refunded_paise + $amountPaise;
            $invoice->update([
                'refunded_paise' => $refunded,
                'status' => $refunded >= $invoice->paid_paise ? 'refunded' : $invoice->status,
            ]);

            return $refund;
        });
    }

    public function cancel(Invoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($invoice, $reason): void {
            $locked = $this->lock($invoice);

            if ($locked->paid_paise > $locked->refunded_paise) {
                throw new BillingException('Refund the payments on this bill before cancelling it.');
            }

            $locked->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            Payment::withoutTenancy()->where('invoice_id', $locked->id)->where('status', 'pending')->update(['status' => 'superseded']);
        });
    }

    private function tellPatient(Invoice $invoice, Payment $payment): void
    {
        $patient = Patient::withoutTenancy()->find($invoice->patient_id);
        $organization = Organization::query()->find($invoice->organization_id);

        if ($patient && $organization) {
            $this->notifier->notifyPatient($patient, 'payment_received', [
                'amount' => Money::format($payment->amount_paise),
                'clinic' => $organization->name,
            ], "payment:{$payment->id}");
        }
    }

    private function assertRefundable(Payment $payment, int $amountPaise): void
    {
        if ($payment->status !== 'captured') {
            throw new BillingException('Only a completed payment can be refunded.');
        }

        if ($amountPaise <= 0 || $amountPaise > $payment->amount_paise - $payment->refundedPaise()) {
            throw new BillingException('The refund is more than what is left on this payment.');
        }
    }

    private function applyPaid(Invoice $invoice, int $amountPaise): void
    {
        $paid = $invoice->paid_paise + $amountPaise;

        $invoice->update([
            'paid_paise' => $paid,
            'status' => $paid >= $invoice->total_paise ? 'paid' : 'partial',
        ]);
    }

    private function lock(Invoice $invoice): Invoice
    {
        return Invoice::withoutTenancy()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * A separate gapless-by-design series per organization, location and financial year.
     */
    private function nextNumber(int $organizationId, int $locationId, bool $isGst): string
    {
        $prefix = $isGst ? 'INV' : 'RCP';
        $year = $this->financialYear(now());
        $sequence = $this->sequence->next($organizationId, "{$prefix}:{$locationId}:{$year}");

        return sprintf('%s/%d/%s/%05d', $prefix, $locationId, $year, $sequence);
    }

    private function financialYear(CarbonInterface $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }
}
