<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Prescription;
use App\Services\AuditLogger;
use App\Services\Billing\InvoicePdf;
use App\Services\PrescriptionPdf;
use App\Tenancy\TenantContext;
use Illuminate\Http\Response;

/**
 * What a WhatsApp or SMS link opens. A patient has no login at that moment, so the signed,
 * expiring URL is the permission, and every download is logged against the record.
 */
class DocumentLinkController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function prescription(int $prescription, PrescriptionPdf $pdf): Response
    {
        $record = Prescription::withoutTenancy()->findOrFail($prescription);
        $this->audit->record('prescriptions.downloaded_by_link', $record);

        $content = $this->tenant->run(Organization::query()->findOrFail($record->organization_id), fn () => $pdf->render($record));

        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$pdf->filename($record).'"', 'Cache-Control' => 'no-store, private']);
    }

    public function invoice(int $invoice, InvoicePdf $pdf): Response
    {
        $record = Invoice::withoutTenancy()->findOrFail($invoice);
        $this->audit->record('invoices.downloaded_by_link', $record);

        $content = $this->tenant->run(Organization::query()->findOrFail($record->organization_id), fn () => $pdf->render($record));

        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$pdf->filename($record).'"', 'Cache-Control' => 'no-store, private']);
    }
}
