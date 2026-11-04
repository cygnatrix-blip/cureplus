<?php

namespace App\Mail;

use App\Models\Prescription;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PrescriptionMail extends Mailable
{
    public function __construct(
        public readonly Prescription $prescription,
        private readonly string $pdf,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your prescription from '.$this->prescription->doctor_name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.prescription');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
