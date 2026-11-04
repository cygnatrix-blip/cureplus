<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\Messaging\Notifier;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

class DocumentShareController extends Controller
{
    public function __construct(private readonly Notifier $notifier, private readonly TenantContext $tenant) {}

    public function prescription(Prescription $prescription): RedirectResponse
    {
        return $this->share((int) $prescription->patient_id, 'prescription', URL::temporarySignedRoute('files.prescription', now()->addHours((int) config('cureplus.document_link_hours')), ['prescription' => $prescription->id]));
    }

    public function invoice(Invoice $invoice): RedirectResponse
    {
        return $this->share((int) $invoice->patient_id, 'bill', URL::temporarySignedRoute('files.invoice', now()->addHours((int) config('cureplus.document_link_hours')), ['invoice' => $invoice->id]));
    }

    private function share(int $patientId, string $document, string $link): RedirectResponse
    {
        $this->notifier->notifyPatient(Patient::query()->findOrFail($patientId), 'document_ready', [
            'document' => $document,
            'clinic' => $this->tenant->organization()->name,
            'link' => $link,
        ]);

        return back()->with('success', 'Sent to the patient.');
    }
}
