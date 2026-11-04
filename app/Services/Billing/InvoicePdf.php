<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'patient', 'location', 'doctor:id,name', 'payments']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'organization' => Organization::query()->findOrFail($invoice->organization_id),
        ])->setPaper('a5')->output();
    }

    public function filename(Invoice $invoice): string
    {
        return str_replace('/', '-', $invoice->number).'.pdf';
    }
}
