<x-mail::message>
# Your bill

Dear {{ $invoice->patient->name }},

Your bill {{ $invoice->number }} is attached as a PDF.
</x-mail::message>
