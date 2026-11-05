    <header class="border-b border-slate-300 pb-3">
        <h2 class="text-xl font-semibold text-slate-900">{{ $letterhead['title'] ?? $prescription->location->name }}</h2>
        <p class="text-sm text-slate-600">{{ $letterhead['subtitle'] ?? '' }} {{ $prescription->location->address_line }} {{ $prescription->location->city }} {{ $prescription->location->phone }}</p>
    </header>
    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-300 py-3 text-sm">
        <div>
            <p class="font-semibold">{{ $prescription->doctor_name }}</p>
            <p>{{ $prescription->doctor_qualification }}</p>
            <p>Reg. no. {{ $prescription->doctor_registration_number ?? 'Not provided' }}</p>
        </div>
        <div class="text-right">
            <p class="font-semibold">{{ $prescription->patient->name }}</p>
            <p>{{ $prescription->patient->patient_code }} · {{ $prescription->patient->ageInYears() }} yrs</p>
            <p>{{ $prescription->issued_at?->format('d M Y') }}</p>
        </div>
    </div>
    @if ($prescription->visit?->diagnosis)<p class="mt-3 text-sm"><strong>Diagnosis:</strong> {{ $prescription->visit->diagnosis }}</p>@endif
    <h3 class="mt-4 text-lg font-semibold">℞ {{ __('rx.medicines', [], $language) }}</h3>
    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm">
        @foreach ($prescription->items as $item)
            <li>
                <span class="font-medium">{{ $item->medicine_name }}</span>
                @if ($item->dosage) · {{ $item->dosage }}@endif
                @if ($item->timing) · {{ __('rx.'.$item->timing, [], $language) }}@endif
                @if ($item->duration_days) · {{ __('rx.days', ['count' => $item->duration_days], $language) }}@endif
                @if ($item->notes)<span class="block text-slate-600">{{ $item->notes }}</span>@endif
            </li>
        @endforeach
    </ol>
    @if ($prescription->lab_advice)
        <h3 class="mt-4 font-semibold">{{ __('rx.tests', [], $language) }}</h3>
        <p class="text-sm whitespace-pre-line">{{ $prescription->lab_advice }}</p>
    @endif
    @if ($prescription->patient_instructions)
        <h3 class="mt-4 font-semibold">{{ __('rx.instructions', [], $language) }}</h3>
        <p class="text-sm whitespace-pre-line">{{ $prescription->patient_instructions }}</p>
    @endif
    @if ($prescription->follow_up_on)
        <p class="mt-4 text-sm"><strong>{{ __('rx.follow_up', [], $language) }}:</strong> {{ $prescription->follow_up_on->format('d M Y') }}</p>
    @endif
    @if (! empty($prescription->location->rx_pad['footer']))<footer class="mt-6 border-t border-slate-300 pt-2 text-xs text-slate-600">{{ $prescription->location->rx_pad['footer'] }}</footer>@endif
