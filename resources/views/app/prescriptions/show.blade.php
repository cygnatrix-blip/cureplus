@php($language = $prescription->instructions_language)
@php($letterhead = $prescription->location->letterhead ?? [])
<x-layouts.app title="Prescription">
    <div class="mb-3 flex flex-wrap items-center justify-end gap-2 print:hidden">
        <x-send-to-patient labelled :patient="$prescription->patient" event="document_ready" :link="$shareLink" :variables="['name' => $prescription->patient->name, 'document' => 'prescription', 'clinic' => $tenant->organization()?->name, 'link' => $shareLink]" />
    </div>
    <div class="mb-4 flex flex-wrap justify-end gap-2 print:hidden">
        <button type="button" class="btn btn-primary" data-print>Print</button>
        <a class="btn btn-secondary" href="{{ route('app.prescriptions.pdf', $prescription) }}">Download PDF</a>
        <form method="POST" action="{{ route('app.prescriptions.email', $prescription) }}">@csrf<button class="btn btn-secondary">Email to patient</button></form>
        <form method="POST" action="{{ route('app.prescriptions.share', $prescription) }}">@csrf<button class="btn btn-secondary">Send automatically</button></form>
        <a class="btn btn-ghost" href="{{ route('app.visits.show', $prescription->visit_id) }}">Back to consultation</a>
    </div>
    <article class="card card-body mx-auto max-w-3xl print:border-0 print:shadow-none">
        @include('partials.prescription-document')
    </article>
</x-layouts.app>
