@php($money = \App\Support\Money::class)
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #ccc; padding: 3px; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h2>{{ $organization->name }}: income for {{ $month->format('F Y') }}</h2>
    <p>Exempt: {{ $money::format($summary['exempt']) }} · Taxable: {{ $money::format($summary['taxable']) }} · GST: {{ $money::format($summary['tax']) }} · Total: {{ $money::format($summary['total']) }} · Collected: {{ $money::format($summary['collected']) }} · Refunded: {{ $money::format($summary['refunded']) }}</p>
    <table>
        <tr><th>Number</th><th>Date</th><th>Patient</th><th>Doctor</th><th class="right">Exempt</th><th class="right">Taxable</th><th class="right">GST</th><th class="right">Total</th><th class="right">Paid</th></tr>
        @foreach ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->number }}</td><td>{{ $invoice->issued_at->format('d/m/Y') }}</td><td>{{ $invoice->patient?->name }}</td><td>{{ $invoice->doctor?->name }}</td>
                <td class="right">{{ $money::plain($invoice->exempt_paise) }}</td><td class="right">{{ $money::plain($invoice->taxable_paise) }}</td><td class="right">{{ $money::plain($invoice->tax_paise) }}</td><td class="right">{{ $money::plain($invoice->total_paise) }}</td><td class="right">{{ $money::plain($invoice->paid_paise) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
