@php
    $letterhead = $prescription->location->letterhead ?? [];
    $visit = $prescription->visit;
    $readings = $visit?->vital?->readings() ?? [];
    $isAlliedPlan = \App\Support\Specialties::isAllied($prescription->doctor_specialty);
    $location = $prescription->location;
    $logo = $prescription->logoDataUri();
    $colour = $prescription->accentColour();
    $address = collect([$location->address_line, $location->city, $location->state, $location->pincode])->filter()->implode(', ');
    $registration = $prescription->doctor_registration_number || ! $isAlliedPlan
        ? 'Reg. no. '.($prescription->doctor_registration_number ?? 'Not provided').($prescription->doctor_registration_council ? ' · '.$prescription->doctor_registration_council : '')
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px 60px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .head { border-bottom: 2px solid {{ $colour }}; padding-bottom: 8px; }
        .clinic { font-size: 17px; font-weight: bold; color: {{ $colour }}; }
        .muted { color: #64748b; font-size: 9.5px; }
        .doctor { font-size: 13px; font-weight: bold; }
        .patient { background: #f1f5f9; margin-top: 10px; }
        .patient td { padding: 5px 7px; }
        .label { color: #64748b; font-size: 8.5px; text-transform: uppercase; }
        .section { margin-top: 10px; }
        .heading { color: {{ $colour }}; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .allergy { margin-top: 10px; padding: 6px 8px; border: 1px solid #fecdd3; background: #fff1f2; color: #881337; }
        .rx-title { margin-top: 14px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; font-size: 13px; font-weight: bold; }
        .rx-mark { color: {{ $colour }}; font-size: 18px; font-style: italic; font-family: DejaVu Serif, serif; }
        .meds th { text-align: left; color: #64748b; font-size: 9px; font-weight: normal; padding: 4px 4px; border-bottom: 1px solid #e2e8f0; }
        .meds td { padding: 5px 4px; border-bottom: 1px solid #f1f5f9; }
        .medicine { font-weight: bold; text-transform: uppercase; }
        .follow { margin-top: 12px; padding: 5px 8px; border: 1px solid {{ $colour }}; display: inline-block; }
        .sign { margin-top: 44px; }
        .sign td.line { border-top: 1px solid #94a3b8; padding-top: 3px; text-align: right; }
        .footer { position: fixed; bottom: -42px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 4px; color: #64748b; font-size: 8.5px; }
    </style>
</head>
<body>
    <div class="footer">
        @if ($prescription->is_teleconsultation)Issued after a video consultation, in line with the Telemedicine Practice Guidelines, 2020.<br>@endif
        @if (! empty($location->rx_pad['footer'])){{ $location->rx_pad['footer'] }}<br>@endif
        {{ $prescription->number() }} · Issued {{ $prescription->issued_at?->format('d M Y, h:i A') }} · Generated electronically. Please show this prescription at every visit.
    </div>

    <table class="head">
        <tr>
            @if ($logo)<td style="width: 56px;"><img src="{{ $logo }}" alt="" style="width: 48px; height: 48px;"></td>@endif
            <td>
                <div class="clinic">{{ $letterhead['title'] ?? $location->name }}</div>
                @if (! empty($letterhead['subtitle']))<div>{{ $letterhead['subtitle'] }}</div>@endif
                @if ($address !== '')<div class="muted">{{ $address }}</div>@endif
                <div class="muted">{{ collect([$location->phone ? 'Ph: '.$location->phone : null, $location->email])->filter()->implode(' · ') }}</div>
            </td>
            <td style="text-align: right; width: 42%;">
                <div class="doctor">{{ $prescription->doctor_name }}</div>
                @if ($prescription->doctor_qualification)<div>{{ $prescription->doctor_qualification }}</div>@endif
                @if ($prescription->doctor_specialty)<div class="muted">{{ $prescription->doctor_specialty }}</div>@endif
                @if ($registration)<div class="muted">{{ $registration }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="patient">
        <tr>
            <td><div class="label">Patient</div><strong>{{ $prescription->patient->name }}</strong></td>
            <td><div class="label">Age / Sex</div>{{ $prescription->patientAgeAndSex() ?: '—' }}</td>
            <td><div class="label">Patient ID</div>{{ $prescription->patient->patient_code }}</td>
        </tr>
        <tr>
            <td><div class="label">Mobile</div>{{ $prescription->patient->mobile ?: '—' }}</td>
            <td><div class="label">Date</div>{{ $prescription->issued_at?->format('d M Y') }}</td>
            <td><div class="label">Prescription no.</div>{{ $prescription->number() }}@if ($prescription->is_teleconsultation) · Video consultation @endif</td>
        </tr>
    </table>

    @if ($prescription->patient->allergies)<div class="allergy"><strong>Allergies:</strong> {{ $prescription->patient->allergies }}</div>@endif

    @if ($visit?->complaints || $visit?->history)
        <table class="section">
            <tr>
                @if ($visit?->complaints)<td style="padding-right: 10px;"><div class="heading">Chief complaints</div>{!! nl2br(e($visit->complaints)) !!}</td>@endif
                @if ($visit?->history)<td><div class="heading">History</div>{!! nl2br(e($visit->history)) !!}</td>@endif
            </tr>
        </table>
    @endif
    @if ($readings !== [])
        <div class="section"><div class="heading">Vitals</div>{{ collect($readings)->map(fn ($value, $label) => "{$label} {$value}")->implode('   ·   ') }}</div>
    @endif
    @if ($visit?->diagnosis)
        <div class="section"><div class="heading">Diagnosis</div><strong>{!! nl2br(e($visit->diagnosis)) !!}</strong></div>
    @endif

    <div class="rx-title">@unless ($isAlliedPlan)<span class="rx-mark">Rx</span>&nbsp; @endunless{{ $isAlliedPlan ? 'Treatment plan' : 'Medicines' }}</div>
    <table class="meds">
        <tr><th style="width: 18px;">#</th><th>Medicine</th><th style="width: 70px;">Dose</th><th style="width: 110px;">When</th><th style="width: 60px;">Duration</th></tr>
        @foreach ($prescription->items as $item)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td><span class="medicine">{{ $item->medicine_name }}</span>@if ($item->notes)<br><span class="muted">{{ $item->notes }}</span>@endif</td>
                <td>{{ $item->dosage }}</td>
                <td>{{ $item->timing ? __('rx.'.$item->timing, [], 'en') : '' }}</td>
                <td>{{ $item->duration_days ? $item->duration_days.' days' : '' }}</td>
            </tr>
        @endforeach
    </table>
    @if ($prescription->usesDoseCounts())<div class="muted" style="margin-top: 3px;">Dose 1-0-1 means morning - afternoon - night.</div>@endif

    @if ($prescription->lab_advice)
        <div class="section"><div class="heading">Tests advised</div>{!! nl2br(e($prescription->lab_advice)) !!}</div>
    @endif
    {{-- DomPDF cannot shape Devanagari or Bengali, so regional text is only on the printed page. --}}
    @if ($prescription->patient_instructions && $prescription->instructions_language === 'en')
        <div class="section"><div class="heading">Instructions</div>{!! nl2br(e($prescription->patient_instructions)) !!}</div>
    @elseif ($prescription->patient_instructions)
        <div class="section muted">Instructions in your language are on the printed copy.</div>
    @endif
    @if ($prescription->follow_up_on)<div class="follow"><strong>Follow-up on:</strong> {{ $prescription->follow_up_on->format('d M Y') }}</div>@endif

    <table class="sign">
        <tr>
            <td style="width: 60%;"></td>
            <td class="line">
                <strong>{{ $prescription->doctor_name }}</strong><br>
                @if ($prescription->doctor_qualification){{ $prescription->doctor_qualification }}<br>@endif
                @if ($registration)<span class="muted">{{ $registration }}</span>@endif
            </td>
        </tr>
    </table>
</body>
</html>
