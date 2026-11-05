<?php

namespace App\Jobs;

use App\Mail\PrescriptionMail;
use App\Models\Prescription;
use App\Services\OrganizationMail;
use App\Services\PrescriptionPdf;
use App\Tenancy\Jobs\RestoresTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPrescriptionEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $organizationId, public readonly int $prescriptionId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RestoresTenant($this->organizationId)];
    }

    public function handle(PrescriptionPdf $pdf, OrganizationMail $mail): void
    {
        $prescription = Prescription::query()->with('patient')->findOrFail($this->prescriptionId);

        $mailer = $mail->mailerFor($this->organizationId);

        if ($mailer !== null && $prescription->patient->email) {
            $mailer->to($prescription->patient->email)->send(new PrescriptionMail($prescription, $pdf->render($prescription), $pdf->filename($prescription)));
        }
    }
}
