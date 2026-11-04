<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\AuditLogger;
use App\Services\Payments\GatewayFactory;
use App\Services\Reports\ReportService;
use App\Support\Money;
use App\Support\XlsxWriter;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports, private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $date = $this->date($request->query('date'), CarbonImmutable::today());
        $from = $this->date($request->query('from'), CarbonImmutable::today()->startOfMonth());
        $to = $this->date($request->query('to'), CarbonImmutable::today());

        return view('app.reports.index', [
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'month' => $request->query('month', $from->format('Y-m')),
            'cash' => $this->reports->dailyCash($date),
            'income' => $this->reports->incomeSummary($from, $to),
            'doctors' => $this->reports->doctorRevenue($from, $to),
            'patients' => $this->reports->patientCounts($from, $to),
            'diagnoses' => $this->reports->topDiagnoses($from, $to),
        ]);
    }

    public function monthlyXlsx(Request $request, AuditLogger $audit): BinaryFileResponse
    {
        $month = $this->month($request);
        $audit->record('reports.exported', $this->tenant->organization(), ['report' => 'monthly_income', 'month' => $month->format('Y-m')]);

        $rows = $this->reports->invoicesForMonth($month)->map(fn (Invoice $invoice) => [
            $invoice->number, $invoice->issued_at->toDateString(), $invoice->patient?->name, $invoice->doctor?->name, $invoice->kind,
            $invoice->exempt_paise / 100, $invoice->taxable_paise / 100, $invoice->tax_paise / 100, $invoice->total_paise / 100,
            $invoice->paid_paise / 100, $invoice->refunded_paise / 100,
        ]);

        $path = XlsxWriter::write('Income '.$month->format('Y-m'), ['Number', 'Date', 'Patient', 'Doctor', 'Type', 'Exempt', 'Taxable', 'GST', 'Total', 'Paid', 'Refunded'], $rows);

        return response()->download($path, 'income-'.$month->format('Y-m').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function monthlyPdf(Request $request): Response
    {
        $month = $this->month($request);

        $pdf = Pdf::loadView('pdf.monthly-income', [
            'organization' => $this->tenant->organization(),
            'month' => $month,
            'invoices' => $this->reports->invoicesForMonth($month),
            'summary' => $this->reports->incomeSummary($month->startOfMonth(), $month->endOfMonth()),
        ])->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="income-'.$month->format('Y-m').'.pdf"',
        ]);
    }

    public function reconciliation(Request $request, GatewayFactory $gateways): View
    {
        $from = $this->date($request->query('from'), CarbonImmutable::today()->subDays(7));
        $to = $this->date($request->query('to'), CarbonImmutable::today());
        $gateway = $gateways->forWebhook((int) $this->tenant->id());
        $result = null;
        $error = null;

        if ($gateway !== null) {
            try {
                $result = $this->reports->reconcile($from, $to, $gateway->listPayments($from->startOfDay()->timestamp, $to->endOfDay()->timestamp));
            } catch (RequestException) {
                $error = 'Razorpay did not respond. Check your keys under Settings.';
            }
        } else {
            $error = 'This report works with Razorpay. If you use Cashfree, compare your payments with your Cashfree dashboard.';
        }

        return view('app.reports.reconciliation', ['from' => $from, 'to' => $to, 'result' => $result, 'error' => $error, 'money' => Money::class]);
    }

    private function month(Request $request): CarbonImmutable
    {
        $value = (string) $request->query('month');

        return preg_match('/^\d{4}-\d{2}$/', $value) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', "{$value}-01")->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();
    }

    private function date(mixed $value, CarbonImmutable $default): CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay()
            : $default;
    }
}
