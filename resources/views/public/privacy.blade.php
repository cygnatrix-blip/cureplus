<x-layouts.guest title="Privacy policy" wide>
    <h1 class="text-xl font-semibold text-slate-900">Privacy policy</h1>
    @if ($text)
        <p class="mt-4 text-sm whitespace-pre-line text-slate-700">{{ $text }}</p>
    @else
    <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Draft. This text must be reviewed by legal counsel before patient data is collected.</p>
    <div class="mt-4 space-y-3 text-sm text-slate-700">
        <p>{{ config('app.name') }} processes personal data on behalf of clinics and doctors to provide OPD management, in line with India's Digital Personal Data Protection Act, 2023.</p>
        <p>Each clinic controls the records it creates. Your records are shared with another clinic only when you give consent, and you can withdraw that consent.</p>
        <p>You can ask for a copy of your data, or ask for it to be corrected or deleted. Medical and billing records may have to be retained where the law requires it.</p>
    </div>
    @endif
</x-layouts.guest>
