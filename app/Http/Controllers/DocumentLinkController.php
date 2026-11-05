<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Prescription;
use App\Services\AuditLogger;
use App\Services\Billing\InvoicePdf;
use App\Services\PrescriptionPdf;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * What a WhatsApp or SMS link opens. A patient has no login at that moment, so the signed,
 * expiring URL is the permission, and every view and download is logged against the record.
 */
class DocumentLinkController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function prescription(Request $request, int $prescription): Response
    {
        $record = Prescription::withoutTenancy()->findOrFail($prescription);
        $organization = Organization::query()->findOrFail($record->organization_id);
        $this->audit->record('prescriptions.viewed_by_link', $record);

        $html = $this->tenant->run($organization, function () use ($record, $organization, $request): string {
            $record->load(['items', 'patient', 'location', 'visit:id,diagnosis']);

            return view('public.document', [
                'organization' => $organization,
                'title' => 'Prescription',
                'partial' => 'partials.prescription-document',
                'prescription' => $record,
                'language' => $record->instructions_language,
                'letterhead' => $record->location->letterhead ?? [],
                'pdfUrl' => URL::temporarySignedRoute('files.prescription.pdf', $this->expiry($request), ['prescription' => $record->id]),
                'expires' => $this->expiry($request),
            ])->render();
        });

        return $this->page($html);
    }

    public function invoice(Request $request, int $invoice): Response
    {
        $record = Invoice::withoutTenancy()->findOrFail($invoice);
        $organization = Organization::query()->findOrFail($record->organization_id);
        $this->audit->record('invoices.viewed_by_link', $record);

        $html = $this->tenant->run($organization, function () use ($record, $organization, $request): string {
            $record->load(['items', 'patient', 'doctor:id,name', 'location']);

            return view('public.document', [
                'organization' => $organization,
                'title' => $record->number,
                'partial' => 'partials.invoice-document',
                'invoice' => $record,
                'money' => Money::class,
                'pdfUrl' => URL::temporarySignedRoute('files.invoice.pdf', $this->expiry($request), ['invoice' => $record->id]),
                'expires' => $this->expiry($request),
            ])->render();
        });

        return $this->page($html);
    }

    public function prescriptionPdf(int $prescription, PrescriptionPdf $pdf): Response
    {
        $record = Prescription::withoutTenancy()->findOrFail($prescription);
        $this->audit->record('prescriptions.downloaded_by_link', $record);

        $content = $this->tenant->run(Organization::query()->findOrFail($record->organization_id), fn () => $pdf->render($record));

        return $this->pdf($content, $pdf->filename($record));
    }

    public function invoicePdf(int $invoice, InvoicePdf $pdf): Response
    {
        $record = Invoice::withoutTenancy()->findOrFail($invoice);
        $this->audit->record('invoices.downloaded_by_link', $record);

        $content = $this->tenant->run(Organization::query()->findOrFail($record->organization_id), fn () => $pdf->render($record));

        return $this->pdf($content, $pdf->filename($record));
    }

    /**
     * The download stops working when the page does, so it cannot outlive the link it came from.
     */
    private function expiry(Request $request): Carbon
    {
        return Carbon::createFromTimestamp((int) $request->query('expires'));
    }

    /**
     * Medical records: never cached, and never listed by a search engine.
     */
    private function page(string $html): Response
    {
        return response($html, 200, ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function pdf(string $content, string $filename): Response
    {
        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
