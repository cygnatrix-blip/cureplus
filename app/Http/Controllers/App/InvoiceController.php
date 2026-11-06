<?php

namespace App\Http\Controllers\App;

use App\Enums\VisitType;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Jobs\SendInvoiceEmail;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServiceItem;
use App\Services\Billing\BillingService;
use App\Services\Billing\FeeResolver;
use App\Services\Billing\InvoicePdf;
use App\Services\Messaging\Notifier;
use App\Services\OrganizationMail;
use App\Services\Payments\GatewayFactory;
use App\Services\TenantDoctors;
use App\Support\Csv;
use App\Support\DocumentLink;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly BillingService $billing,
    ) {}

    public function index(Request $request): View
    {
        return view('app.invoices.index', [
            'invoices' => $this->query($request)->with(['patient:id,name,patient_code', 'doctor:id,name'])->paginate(25)->withQueryString(),
            'search' => $request->query('q'),
            'status' => $request->query('status'),
            'date' => $request->query('date'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->with(['patient:id,name', 'doctor:id,name'])->limit(10000)->get()->map(fn (Invoice $invoice) => [
            $invoice->number, $invoice->issued_at->toDateString(), $invoice->patient?->name, $invoice->doctor?->name, $invoice->status,
            Money::decimal($invoice->total_paise), Money::decimal($invoice->paid_paise), Money::decimal($invoice->refunded_paise),
        ]);

        return Csv::download('bills.csv', ['Number', 'Date', 'Patient', 'Doctor', 'Status', 'Total', 'Paid', 'Refunded'], $rows);
    }

    public function create(Request $request, TenantDoctors $doctors): View
    {
        $appointment = $request->integer('appointment') ? Appointment::query()->findOrFail($request->integer('appointment')) : null;
        $patientId = $appointment->patient_id ?? ($request->integer('patient') ?: null);

        return view('app.invoices.create', [
            'patient' => $patientId ? Patient::query()->findOrFail($patientId) : null,
            'appointment' => $appointment,
            'doctors' => $doctors->all($this->tenant->location()?->id),
            'serviceItems' => ServiceItem::query()->where('is_active', true)->orderBy('name')->get(),
            'canDiscount' => Gate::check('billing.refund'),
        ]);
    }

    public function fee(Request $request, FeeResolver $fees): JsonResponse
    {
        $data = $request->validate([
            'doctor_user_id' => ['nullable', 'integer'],
            'visit_type' => ['required', Rule::enum(VisitType::class)],
            'patient_id' => ['nullable', 'integer'],
        ]);

        $fee = $fees->resolve($this->tenant->organization(), $data['doctor_user_id'] ?? null, VisitType::from($data['visit_type']), $data['patient_id'] ?? null);

        return response()->json(['amount' => Money::decimal($fee['amount_paise']), 'note' => $fee['note']]);
    }

    public function store(Request $request, TenantDoctors $doctors): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where('organization_id', $this->tenant->id())->whereNull('deleted_at')],
            'doctor_user_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer', Rule::exists('appointments', 'id')->where('organization_id', $this->tenant->id())],
            'items_json' => ['required', 'json'],
            'discount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'pay_mode' => ['nullable', Rule::in(['cash', 'upi', 'card'])],
            'pay_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'pay_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $discount = Money::toPaise($data['discount'] ?? null);

        // Discounts are money leaving the clinic, so they need a permission and a stated reason.
        if ($discount > 0 && ! Gate::check('billing.refund')) {
            abort(403, 'You are not allowed to give discounts.');
        }

        if ($discount > 0 && blank($data['discount_reason'] ?? null)) {
            throw ValidationException::withMessages(['discount_reason' => 'Give a reason for the discount.']);
        }

        if (! empty($data['doctor_user_id']) && ! $doctors->isDoctor((int) $data['doctor_user_id'])) {
            throw ValidationException::withMessages(['doctor_user_id' => 'Choose a doctor from your organization.']);
        }

        try {
            $invoice = $this->billing->createInvoice($this->tenant->organization(), Patient::query()->findOrFail($data['patient_id']), $this->lines($data['items_json']), [
                'location_id' => (int) $this->tenant->location()?->id,
                'doctor_user_id' => $data['doctor_user_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'discount_paise' => $discount,
                'discount_reason' => $data['discount_reason'] ?? null,
            ]);
        } catch (BillingException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $payAmount = Money::toPaise($data['pay_amount'] ?? null);

        if (! empty($data['pay_mode']) && $payAmount > 0) {
            try {
                $this->billing->recordPayment($invoice, $payAmount, $data['pay_mode'], $data['pay_reference'] ?? null);
            } catch (BillingException $exception) {
                return redirect()->route('app.invoices.show', $invoice)->with('error', "Bill created, but the payment was not taken: {$exception->getMessage()}");
            }
        }

        return redirect()->route('app.invoices.show', $invoice)->with('success', "Saved {$invoice->number}.");
    }

    public function show(Invoice $invoice, GatewayFactory $gateways): View
    {
        $invoice->load(['items', 'patient', 'doctor:id,name', 'location', 'payments', 'refunds']);

        return view('app.invoices.show', [
            'invoice' => $invoice,
            'organization' => $this->tenant->organization(),
            'onlineEnabled' => $gateways->forOrganization((int) $this->tenant->id()) !== null,
            'shareLink' => DocumentLink::invoice($invoice->id),
        ]);
    }

    public function pdf(Invoice $invoice, InvoicePdf $pdf): Response
    {
        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
        ]);
    }

    public function email(Invoice $invoice, OrganizationMail $mail): RedirectResponse
    {
        $invoice->load('patient:id,email');

        if (! $invoice->patient->email) {
            return back()->with('error', 'This patient has no email address on file.');
        }

        if (! $mail->hasOwnEmail((int) $this->tenant->id())) {
            return back()->with('error', 'Connect your own email under Integrations first. Patient emails are sent from your address.');
        }

        SendInvoiceEmail::dispatch((int) $this->tenant->id(), $invoice->id);

        return back()->with('success', 'Bill queued for email.');
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['cash', 'upi', 'card'])],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->attempt(fn () => $this->billing->recordPayment($invoice, Money::toPaise($data['amount']), $data['mode'], $data['reference'] ?? null), 'Payment recorded.');
    }

    public function paymentLink(Invoice $invoice, GatewayFactory $gateways, Notifier $notifier): RedirectResponse
    {
        $gateway = $gateways->forOrganization((int) $this->tenant->id());

        if ($gateway === null) {
            return back()->with('error', 'Set up online payments under Integrations first.');
        }

        try {
            $payment = $this->billing->createPaymentLink($invoice, $gateway);
        } catch (RequestException) {
            return back()->with('error', 'The payment provider could not create the link. Check your keys under Integrations.');
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $patient = Patient::query()->findOrFail($invoice->patient_id);

        if (! $notifier->canReachPatient($patient)) {
            return back()->with('success', 'Payment link created, but it could not be sent: connect your email under Integrations. For now, share this link yourself: '.$payment->gateway_link_url);
        }

        $notifier->notifyPatient($patient, 'payment_link', [
            'amount' => Money::format($payment->amount_paise),
            'clinic' => $this->tenant->organization()->name,
            'link' => (string) $payment->gateway_link_url,
        ]);

        return back()->with('success', 'Payment link sent to the patient.');
    }

    public function refund(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $payment = Payment::query()->where('invoice_id', $invoice->id)->findOrFail($data['payment_id']);

        return $this->attempt(fn () => $this->billing->refund($payment, Money::toPaise($data['amount']), $data['reason']), 'Refund recorded.');
    }

    /**
     * Staff found the patient's UPI payment in their bank app.
     */
    public function confirmUpi(Invoice $invoice, Payment $payment): RedirectResponse
    {
        abort_unless((int) $payment->invoice_id === $invoice->id && $payment->mode === 'upi', 404);

        return $this->attempt(fn () => $this->billing->confirmUpiPayment($payment), 'UPI payment confirmed.');
    }

    public function rejectUpi(Invoice $invoice, Payment $payment): RedirectResponse
    {
        abort_unless((int) $payment->invoice_id === $invoice->id && $payment->mode === 'upi', 404);

        $this->billing->rejectUpiPayment($payment);

        return back()->with('success', 'Marked as not received. The patient is told, and can send the transaction ID again.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->attempt(fn () => $this->billing->cancel($invoice, $data['reason']), 'Bill cancelled.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (RequestException) {
            return back()->with('error', 'Razorpay refused the request. Nothing was recorded.');
        }

        return back()->with('success', $success);
    }

    /**
     * Prices may be edited at the desk, but tax treatment always comes from the
     * server's own service item: the browser cannot make a taxable item exempt.
     *
     * @return list<array{service_item_id: int|null, description: string, sac_hsn_code: string|null, quantity: int, unit_paise: int, is_taxable: bool, gst_rate_bp: int}>
     */
    private function lines(string $json): array
    {
        $validator = Validator::make(['items' => json_decode($json, true)], [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.service_item_id' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'between:1,1000'],
            'items.*.unit' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], ['items.required' => 'Add at least one item.', 'items.min' => 'Add at least one item.']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['items_json' => $validator->errors()->first()]);
        }

        /** @var list<array{service_item_id?: int|null, description: string, quantity: int, unit: string}> $rows */
        $rows = $validator->validated()['items'];
        $serviceItems = ServiceItem::query()->whereIn('id', array_filter(array_column($rows, 'service_item_id')))->get()->keyBy('id');

        return collect($rows)->map(function (array $item) use ($serviceItems): array {
            $id = $item['service_item_id'] ?? null;
            $service = $id ? $serviceItems->get($id) : null;

            if ($id && $service === null) {
                throw ValidationException::withMessages(['items_json' => 'That service item is not available.']);
            }

            try {
                $unit = Money::toPaise($item['unit']);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['items_json' => 'Enter a valid price.']);
            }

            return [
                'service_item_id' => $service?->id,
                'description' => $item['description'],
                'sac_hsn_code' => $service?->sac_hsn_code,
                'quantity' => (int) $item['quantity'],
                'unit_paise' => $unit,
                'is_taxable' => (bool) $service?->is_taxable,
                'gst_rate_bp' => (int) ($service->gst_rate_bp ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return Builder<Invoice>
     */
    private function query(Request $request): Builder
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');
        $date = $request->query('date');

        return Invoice::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($query) => $query->search($search))))
            ->when(in_array($status, ['unpaid', 'partial', 'paid', 'cancelled', 'refunded'], true), fn ($query) => $query->where('status', $status))
            ->when($status === 'upi_to_check', fn ($query) => $query->whereHas('payments', fn ($query) => $query->where('status', Payment::UNVERIFIED)))
            ->when(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, fn ($query) => $query->whereDate('issued_at', $date))
            ->latest('id');
    }
}
