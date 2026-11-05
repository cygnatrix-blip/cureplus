<?php

namespace App\Http\Controllers\Patient;

use App\Contracts\PaymentGateway;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Exceptions\BillingException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consent;
use App\Models\DataRequest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PatientAccount;
use App\Services\AppointmentService;
use App\Services\AuditLogger;
use App\Services\Billing\BillingService;
use App\Services\Billing\FeeResolver;
use App\Services\Billing\InvoicePdf;
use App\Services\DoctorCalendar;
use App\Services\Payments\GatewayFactory;
use App\Services\Portal\PatientRecords;
use App\Services\Portal\PortalBrand;
use App\Services\PrescriptionPdf;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function __construct(
        private readonly PatientRecords $records,
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    public function home(Request $request, PortalBrand $brand): View
    {
        $account = $this->account($request);

        return view('patient.home', [
            'account' => $account,
            'upcoming' => $this->records->upcomingAppointments($account),
            'family' => $this->records->patients($account),
            'prescriptionCount' => $this->records->prescriptions($account)->count(),
            'prescriptions' => $this->records->prescriptions($account)->take(3),
            'dueCount' => $this->records->invoices($account)->filter(fn (Invoice $invoice) => $invoice->duePaise() > 0)->count(),
            'bookUrl' => $this->bookingUrl($account, $brand),
        ]);
    }

    public function appointments(Request $request): View
    {
        return view('patient.appointments', ['appointments' => $this->records->appointments($this->account($request))]);
    }

    public function appointment(Request $request, int $id, GatewayFactory $gateways): View
    {
        $appointment = $this->records->appointment($this->account($request), $id);
        $date = $this->date($request->query('date'), $appointment->scheduled_on->toImmutable());

        return view('patient.appointment', [
            'appointment' => $appointment,
            'canChange' => $this->canChange($appointment),
            'date' => $date,
            'slots' => $this->canChange($appointment)
                ? app(DoctorCalendar::class)->slots((int) $appointment->doctor_user_id, (int) $appointment->organization_id, (int) $appointment->location_id, $date)
                : [],
            'canPay' => $gateways->forOrganization((int) $appointment->organization_id) !== null && $appointment->status->holdsSlot(),
        ]);
    }

    public function cancel(Request $request, int $id, AppointmentService $appointments): RedirectResponse
    {
        $appointment = $this->records->appointment($this->account($request), $id);
        abort_unless($this->canChange($appointment), 422);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $appointments->cancel($appointment, $data['reason']);

        return redirect()->route('patient.appointments')->with('success', __('Your appointment was cancelled.'));
    }

    public function reschedule(Request $request, int $id, AppointmentService $appointments): RedirectResponse
    {
        $appointment = $this->records->appointment($this->account($request), $id);
        abort_unless($this->canChange($appointment), 422);
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d H:i']]);

        try {
            $appointments->reschedule($appointment, CarbonImmutable::createFromFormat('Y-m-d H:i', $data['starts_at'])->startOfMinute());
        } catch (SlotUnavailableException) {
            throw ValidationException::withMessages(['starts_at' => __('That slot has just been taken. Please choose another.')]);
        }

        return back()->with('success', __('Your appointment was moved.'));
    }

    /**
     * Razorpay's hosted page is reached with an ordinary link, because the CSP's form-action
     * rule would block a form that redirects off-site. No key ever reaches the browser.
     */
    public function payAppointment(Request $request, int $id, BillingService $billing, FeeResolver $fees, GatewayFactory $gateways): View|RedirectResponse
    {
        $appointment = $this->records->appointment($this->account($request), $id);
        $organization = $appointment->organization()->firstOrFail();
        $gateway = $gateways->forOrganization($organization->id);

        abort_if($gateway === null, 404);
        // A cancelled appointment has nothing left to pay for, so no money is taken for it.
        abort_unless($appointment->status->holdsSlot(), 422);

        $invoice = Invoice::withoutTenancy()->where('appointment_id', $appointment->id)->where('status', '!=', 'cancelled')->first();

        if ($invoice === null) {
            $fee = $fees->resolve($organization, (int) $appointment->doctor_user_id, $appointment->visit_type ?? VisitType::New, (int) $appointment->patient_id);
            $invoice = $billing->createInvoice($organization, $appointment->patient, [
                ['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => $fee['amount_paise'], 'is_taxable' => false, 'gst_rate_bp' => 0],
            ], ['location_id' => (int) $appointment->location_id, 'doctor_user_id' => (int) $appointment->doctor_user_id, 'appointment_id' => $appointment->id]);
        }

        return $this->startPayment($invoice, $billing, $gateway);
    }

    public function payInvoice(Request $request, int $id, BillingService $billing, GatewayFactory $gateways): View|RedirectResponse
    {
        $invoice = $this->records->invoice($this->account($request), $id);
        $gateway = $gateways->forOrganization((int) $invoice->organization_id);

        abort_if($gateway === null, 404);

        return $this->startPayment($invoice, $billing, $gateway);
    }

    public function records(Request $request, GatewayFactory $gateways): View
    {
        $account = $this->account($request);
        $invoices = $this->records->invoices($account);

        return view('patient.records', [
            'prescriptions' => $this->records->prescriptions($account),
            'invoices' => $invoices,
            'reports' => $this->records->reports($account),
            'payable' => $invoices->pluck('organization_id')->unique()->filter(fn ($id) => $gateways->forOrganization((int) $id) !== null)->values()->all(),
        ]);
    }

    public function prescriptionPdf(Request $request, int $id, PrescriptionPdf $pdf): Response
    {
        $prescription = $this->records->prescription($this->account($request), $id);
        $this->audit->record('prescriptions.downloaded_by_patient', $prescription);

        $content = $this->tenant->run($this->organizationOf((int) $prescription->organization_id), fn () => $pdf->render($prescription));

        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$pdf->filename($prescription).'"']);
    }

    public function invoicePdf(Request $request, int $id, InvoicePdf $pdf): Response
    {
        $invoice = $this->records->invoice($this->account($request), $id);
        $organization = $this->organizationOf((int) $invoice->organization_id);

        $content = $this->tenant->run($organization, fn () => $pdf->render($invoice));

        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"']);
    }

    public function report(Request $request, int $id): StreamedResponse
    {
        $report = $this->records->report($this->account($request), $id);
        $this->audit->record('visit_attachments.downloaded_by_patient', $report);

        return Storage::disk($report->disk)->download($report->path, $report->original_name, ['Content-Type' => $report->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function consents(Request $request): View
    {
        return view('patient.consents', [
            'consents' => Consent::query()->where('patient_account_id', $this->account($request)->id)->with(['granteeOrganization:id,name', 'granteeDoctor:id,name'])->latest('id')->get(),
        ]);
    }

    public function revokeConsent(Request $request, int $id): RedirectResponse
    {
        $consent = Consent::query()->where('patient_account_id', $this->account($request)->id)->findOrFail($id);
        $consent->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('consents.revoked', $this->account($request), ['consent_id' => $consent->id], (int) $consent->grantee_organization_id);

        return back()->with('success', __('Sharing has been stopped.'));
    }

    public function privacy(Request $request): View
    {
        return view('patient.privacy', [
            'requests' => DataRequest::query()->where('patient_account_id', $this->account($request)->id)->latest('id')->get(),
        ]);
    }

    public function downloadData(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $this->audit->record('patients.data_exported', $account);

        return response()->json($this->records->export($account), 200, [
            'Content-Disposition' => 'attachment; filename="my-health-data.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function storeDataRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:correction,deletion'],
            'details' => ['required_if:type,correction', 'nullable', 'string', 'max:2000'],
        ]);

        DataRequest::query()->create($data + ['patient_account_id' => $this->account($request)->id]);

        return back()->with('success', __('We have received your request.'));
    }

    public function setLanguage(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(config('cureplus.locales')))]]);

        $request->session()->put('locale', $data['locale']);
        $request->user('patient')?->forceFill(['locale' => $data['locale']])->save();

        return back();
    }

    private function startPayment(Invoice $invoice, BillingService $billing, PaymentGateway $gateway): View|RedirectResponse
    {
        try {
            $payment = $billing->createPaymentLink($invoice, $gateway);
        } catch (BillingException) {
            return back()->with('error', __('Nothing is due on this bill.'));
        } catch (RequestException) {
            return back()->with('error', __('Online payment is not available right now. Please pay at the clinic.'));
        }

        return view('patient.pay', ['payment' => $payment, 'invoice' => $invoice]);
    }

    /**
     * The clinic they came through, or the one they know from their records.
     */
    private function bookingUrl(PatientAccount $account, PortalBrand $brand): ?string
    {
        $organization = $brand->current() ?? $this->records->patients($account)->first()?->organization;

        return $organization ? route('booking.show', $organization->slug) : null;
    }

    private function canChange(Appointment $appointment): bool
    {
        return $appointment->status === AppointmentStatus::Booked && $appointment->starts_at !== null && $appointment->starts_at->isFuture();
    }

    private function organizationOf(int $id): Organization
    {
        return Organization::query()->findOrFail($id);
    }

    private function account(Request $request): PatientAccount
    {
        /** @var PatientAccount */
        return $request->user('patient');
    }

    private function date(mixed $value, CarbonImmutable $default): CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay()
            : $default;
    }
}
