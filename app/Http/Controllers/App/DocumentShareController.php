<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\Messaging\Notifier;
use App\Support\DocumentLink;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

class DocumentShareController extends Controller
{
    public function __construct(private readonly Notifier $notifier, private readonly TenantContext $tenant) {}

    public function prescription(Prescription $prescription): RedirectResponse
    {
        return $this->share((int) $prescription->patient_id, 'prescription', DocumentLink::prescription($prescription->id));
    }

    public function invoice(Invoice $invoice): RedirectResponse
    {
        return $this->share((int) $invoice->patient_id, 'bill', DocumentLink::invoice($invoice->id));
    }

    private function share(int $patientId, string $document, string $link): RedirectResponse
    {
        $patient = Patient::query()->findOrFail($patientId);

        if (! $this->notifier->canReachPatient($patient)) {
            return back()->with('error', 'We have no way to send this yet. Connect your email under Integrations, or download it and hand it to the patient.');
        }

        $this->notifier->notifyPatient($patient, 'document_ready', [
            'document' => $document,
            'clinic' => $this->tenant->organization()->name,
            'link' => $link,
        ]);

        return back()->with('success', 'Sent to the patient.');
    }
}
