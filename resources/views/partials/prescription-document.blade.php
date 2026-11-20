{{-- One prescription layout for the clinic screen, printing and shared links. pdf/prescription.blade.php mirrors it for DomPDF. --}}
@php
    $visit = $prescription->visit;
    $readings = $visit?->vital?->readings() ?? [];
    $isAlliedPlan = \App\Support\Specialties::isAllied($prescription->doctor_specialty);
    $location = $prescription->location;
    $logo = $prescription->logoDataUri();
    $address = collect([$location->address_line, $location->city, $location->state, $location->pincode])->filter()->implode(', ');
    $registration = $prescription->doctor_registration_number || ! $isAlliedPlan
        ? 'Reg. no. '.($prescription->doctor_registration_number ?? 'Not provided').($prescription->doctor_registration_council ? ' · '.$prescription->doctor_registration_council : '')
        : null;
@endphp
<div class="text-sm leading-relaxed text-slate-800">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-brand-600 pb-3">
        <div class="flex min-w-0 items-start gap-3">
            @if ($logo)<img src="{{ $logo }}" alt="" class="h-14 w-14 flex-none rounded-lg object-contain">@endif
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-brand-700">{{ $letterhead['title'] ?? $location->name }}</h2>
                @if (! empty($letterhead['subtitle']))<p class="text-slate-600">{{ $letterhead['subtitle'] }}</p>@endif
                @if ($address !== '')<p class="text-xs text-slate-600">{{ $address }}</p>@endif
                <p class="text-xs text-slate-600">{{ collect([$location->phone ? 'Ph: '.$location->phone : null, $location->email])->filter()->implode(' · ') }}</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-base font-semibold text-slate-900">{{ $prescription->doctor_name }}</p>
            @if ($prescription->doctor_qualification)<p>{{ $prescription->doctor_qualification }}</p>@endif
            @if ($prescription->doctor_specialty)<p class="text-slate-600">{{ $prescription->doctor_specialty }}</p>@endif
            @if ($registration)<p class="text-xs text-slate-600">{{ $registration }}</p>@endif
        </div>
    </header>

    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-slate-50 p-3 sm:grid-cols-3 print:bg-transparent print:px-0">
        <div class="col-span-2 sm:col-span-1"><dt class="text-xs text-slate-500">Patient</dt><dd class="font-semibold text-slate-900">{{ $prescription->patient->name }}</dd></div>
        <div><dt class="text-xs text-slate-500">Age / Sex</dt><dd>{{ $prescription->patientAgeAndSex() ?: '—' }}</dd></div>
        <div><dt class="text-xs text-slate-500">Patient ID</dt><dd>{{ $prescription->patient->patient_code }}</dd></div>
        @if ($prescription->patient->mobile)<div><dt class="text-xs text-slate-500">Mobile</dt><dd>{{ $prescription->patient->mobile }}</dd></div>@endif
        <div><dt class="text-xs text-slate-500">Date</dt><dd>{{ $prescription->issued_at?->format('d M Y') }}</dd></div>
        <div><dt class="text-xs text-slate-500">Prescription no.</dt><dd>{{ $prescription->number() }}</dd></div>
    </dl>
    @if ($prescription->is_teleconsultation)<p class="mt-2"><span class="badge badge-slate">{{ __('rx.teleconsultation', [], $language) }}</span></p>@endif

    @if ($prescription->patient->allergies)
        <p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 font-medium text-rose-900"><strong>Allergies:</strong> {{ $prescription->patient->allergies }}</p>
    @endif

    @if ($visit?->complaints || $visit?->history || $readings !== [] || $visit?->diagnosis)
        <section class="mt-3 space-y-2">
            <div class="grid gap-3 sm:grid-cols-2">
                @if ($visit?->complaints)<div><h3 class="text-xs font-semibold uppercase tracking-wide text-brand-700">Chief complaints</h3><p class="whitespace-pre-line">{{ $visit->complaints }}</p></div>@endif
                @if ($visit?->history)<div><h3 class="text-xs font-semibold uppercase tracking-wide text-brand-700">History</h3><p class="whitespace-pre-line">{{ $visit->history }}</p></div>@endif
            </div>
            @if ($readings !== [])
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-brand-700">Vitals</h3>
                    <p class="flex flex-wrap gap-x-4 gap-y-1">@foreach ($readings as $label => $value)<span class="whitespace-nowrap"><span class="text-slate-500">{{ $label }}</span> {{ $value }}</span>@endforeach</p>
                </div>
            @endif
            @if ($visit?->diagnosis)<div><h3 class="text-xs font-semibold uppercase tracking-wide text-brand-700">Diagnosis</h3><p class="whitespace-pre-line font-semibold text-slate-900">{{ $visit->diagnosis }}</p></div>@endif
        </section>
    @endif

    <section class="mt-4">
        <h3 class="flex items-baseline gap-2 border-b border-slate-200 pb-1 font-semibold text-slate-900">
            @unless ($isAlliedPlan)<span class="font-serif text-2xl leading-none text-brand-700" aria-hidden="true">℞</span>@endunless
            {{ $isAlliedPlan ? __('rx.plan', [], $language) : __('rx.medicines', [], $language) }}
        </h3>
        <div class="overflow-x-auto">
            <table class="mt-1 w-full text-left">
                <thead class="text-xs text-slate-500">
                    <tr><th class="py-1 pr-2 font-medium">#</th><th class="py-1 pr-2 font-medium">Medicine</th><th class="py-1 pr-2 font-medium">Dose</th><th class="py-1 pr-2 font-medium">When</th><th class="py-1 font-medium">Duration</th></tr>
                </thead>
                <tbody>
                    @foreach ($prescription->items as $item)
                        <tr class="border-t border-slate-100 align-top">
                            <td class="py-1.5 pr-2 text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-1.5 pr-2">
                                <span class="font-semibold uppercase text-slate-900">{{ $item->medicine_name }}</span>
                                @if ($item->notes)<span class="block text-xs text-slate-600">{{ $item->notes }}</span>@endif
                            </td>
                            <td class="whitespace-nowrap py-1.5 pr-2">{{ $item->dosage }}</td>
                            <td class="py-1.5 pr-2">{{ $item->timing ? __('rx.'.$item->timing, [], $language) : '' }}</td>
                            <td class="whitespace-nowrap py-1.5">{{ $item->duration_days ? __('rx.days', ['count' => $item->duration_days], $language) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($prescription->usesDoseCounts())<p class="mt-1 text-xs text-slate-500">Dose 1-0-1 means morning - afternoon - night.</p>@endif
    </section>

    @if ($prescription->lab_advice)
        <section class="mt-4"><h3 class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('rx.tests', [], $language) }}</h3><p class="whitespace-pre-line">{{ $prescription->lab_advice }}</p></section>
    @endif
    @if ($prescription->patient_instructions)
        <section class="mt-4"><h3 class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('rx.instructions', [], $language) }}</h3><p class="whitespace-pre-line">{{ $prescription->patient_instructions }}</p></section>
    @endif
    @if ($prescription->follow_up_on)
        <p class="mt-4 inline-block rounded-lg border border-brand-600 px-3 py-1.5"><strong>{{ __('rx.follow_up', [], $language) }}:</strong> {{ $prescription->follow_up_on->format('d M Y') }}</p>
    @endif

    <div class="mt-10 flex justify-end">
        <div class="min-w-48 border-t border-slate-400 pt-1 text-right">
            <p class="font-semibold text-slate-900">{{ $prescription->doctor_name }}</p>
            @if ($prescription->doctor_qualification)<p class="text-xs">{{ $prescription->doctor_qualification }}</p>@endif
            @if ($registration)<p class="text-xs text-slate-600">{{ $registration }}</p>@endif
        </div>
    </div>

    <footer class="mt-6 space-y-1 border-t border-slate-300 pt-2 text-xs text-slate-500">
        @if ($prescription->is_teleconsultation)<p>Issued after a video consultation, in line with the Telemedicine Practice Guidelines, 2020.</p>@endif
        @if (! empty($location->rx_pad['footer']))<p class="text-slate-600">{{ $location->rx_pad['footer'] }}</p>@endif
        <p>{{ $prescription->number() }} · Issued {{ $prescription->issued_at?->format('d M Y, h:i A') }} · Generated electronically. Please show this prescription at every visit.</p>
    </footer>
</div>
