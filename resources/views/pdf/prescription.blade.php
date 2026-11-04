@php($letterhead = $prescription->location->letterhead ?? [])
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #555; font-size: 11px; }
        .rule { border-top: 1px solid #333; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border-bottom: 1px solid #ccc; padding: 5px; text-align: left; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.logo', ['organizationId' => $prescription->organization_id])
    <h1>{{ $letterhead['title'] ?? $prescription->location->name }}</h1>
    <div class="muted">{{ $letterhead['subtitle'] ?? '' }} {{ $prescription->location->address_line }} {{ $prescription->location->city }} {{ $prescription->location->phone }}</div>
    <div class="rule"></div>
    <strong>{{ $prescription->doctor_name }}</strong> {{ $prescription->doctor_qualification }}<br>
    <span class="muted">Reg. no. {{ $prescription->doctor_registration_number ?? 'Not provided' }}</span>
    <div class="rule"></div>
    {{ $prescription->patient->name }} · {{ $prescription->patient->patient_code }}
    @if ($prescription->patient->ageInYears() !== null) · {{ $prescription->patient->ageInYears() }} yrs @endif
    · {{ $prescription->issued_at?->format('d M Y') }}
    @if ($prescription->visit->diagnosis)<p><strong>Diagnosis:</strong> {{ $prescription->visit->diagnosis }}</p>@endif
    <h3>Rx</h3>
    <table>
        <tr><th>Medicine</th><th>Dose</th><th>When</th><th>Days</th><th>Notes</th></tr>
        @foreach ($prescription->items as $item)
            <tr>
                <td>{{ $item->medicine_name }}</td>
                <td>{{ $item->dosage }}</td>
                <td>{{ $item->timing ? __('rx.'.$item->timing, [], 'en') : '' }}</td>
                <td>{{ $item->duration_days }}</td>
                <td>{{ $item->notes }}</td>
            </tr>
        @endforeach
    </table>
    @if ($prescription->lab_advice)<p><strong>Tests advised:</strong><br>{!! nl2br(e($prescription->lab_advice)) !!}</p>@endif
    {{-- DomPDF cannot shape Devanagari or Bengali, so regional text is only on the printed page. --}}
    @if ($prescription->patient_instructions && $prescription->instructions_language === 'en')
        <p><strong>Instructions:</strong><br>{!! nl2br(e($prescription->patient_instructions)) !!}</p>
    @elseif ($prescription->patient_instructions)
        <p class="muted">Instructions in your language are on the printed copy.</p>
    @endif
    @if ($prescription->follow_up_on)<p><strong>Follow-up on:</strong> {{ $prescription->follow_up_on->format('d M Y') }}</p>@endif
    <div class="footer">{{ $prescription->location->rx_pad['footer'] ?? '' }}</div>
</body>
</html>
