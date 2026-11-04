<x-mail::message>
# Your prescription

Dear {{ $prescription->patient->name }},

Your prescription from {{ $prescription->doctor_name }} is attached as a PDF.

Please contact your clinic if you have any questions.
</x-mail::message>
