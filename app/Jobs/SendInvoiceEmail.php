<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\Billing\InvoicePdf;
use App\Services\OrganizationMail;
use App\Tenancy\Jobs\RestoresTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $organizationId, public readonly int $invoiceId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RestoresTenant($this->organizationId)];
    }

    public function handle(InvoicePdf $pdf, OrganizationMail $mail): void
    {
        $invoice = Invoice::query()->with('patient')->findOrFail($this->invoiceId);

        $mailer = $mail->mailerFor($this->organizationId);

        if ($mailer !== null && $invoice->patient->email) {
            $mailer->to($invoice->patient->email)->send(new InvoiceMail($invoice, $pdf->render($invoice), $pdf->filename($invoice)));
        }
    }
}
