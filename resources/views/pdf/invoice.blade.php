@php($money = \App\Support\Money::class)
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 15px; margin: 0; }
        .muted { color: #555; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #ccc; padding: 4px; text-align: left; }
        .right { text-align: right; }
        .totals td { border: 0; padding: 2px 4px; }
    </style>
</head>
<body>
    @include('pdf.partials.logo', ['organizationId' => $organization->id])
    <h1>{{ $organization->name }}</h1>
    <div class="muted">{{ $invoice->location->name }} {{ $invoice->location->address_line }} {{ $invoice->location->city }}</div>
    @if ($invoice->gstin_snapshot)<div class="muted">GSTIN: {{ $invoice->gstin_snapshot }}</div>@endif
    <p><strong>{{ $invoice->isGstInvoice() ? 'Tax invoice' : 'Receipt' }} {{ $invoice->number }}</strong><br>
    Date: {{ $invoice->issued_at->format('d M Y') }}<br>
    Patient: {{ $invoice->patient->name }} ({{ $invoice->patient->patient_code }})<br>
    @if ($invoice->doctor)Doctor: {{ $invoice->doctor->name }}@endif</p>
    <table>
        <tr><th>Item</th><th class="right">Qty</th><th class="right">Rate</th>@if ($invoice->isGstInvoice())<th class="right">GST</th>@endif<th class="right">Amount</th></tr>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}@if ($item->sac_hsn_code) <span class="muted">({{ $item->sac_hsn_code }})</span>@endif</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ $money::plain($item->unit_paise) }}</td>
                @if ($invoice->isGstInvoice())<td class="right">{{ $item->gst_rate_bp / 100 }}%</td>@endif
                <td class="right">{{ $money::plain($item->line_total_paise) }}</td>
            </tr>
        @endforeach
    </table>
    <table class="totals">
        <tr><td class="right">Subtotal</td><td class="right">{{ $money::plain($invoice->subtotal_paise) }}</td></tr>
        @if ($invoice->discount_paise)<tr><td class="right">Discount ({{ $invoice->discount_reason }})</td><td class="right">-{{ $money::plain($invoice->discount_paise) }}</td></tr>@endif
        @if ($invoice->tax_paise)
            <tr><td class="right">CGST</td><td class="right">{{ $money::plain((int) floor($invoice->tax_paise / 2)) }}</td></tr>
            <tr><td class="right">SGST</td><td class="right">{{ $money::plain((int) ceil($invoice->tax_paise / 2)) }}</td></tr>
        @endif
        <tr><td class="right"><strong>Total</strong></td><td class="right"><strong>{{ $money::plain($invoice->total_paise) }}</strong></td></tr>
        <tr><td class="right">Paid</td><td class="right">{{ $money::plain($invoice->paid_paise) }}</td></tr>
        @if ($invoice->refunded_paise)<tr><td class="right">Refunded</td><td class="right">{{ $money::plain($invoice->refunded_paise) }}</td></tr>@endif
        @if ($invoice->duePaise())<tr><td class="right">Due</td><td class="right">{{ $money::plain($invoice->duePaise()) }}</td></tr>@endif
    </table>
    @if ($invoice->status === 'cancelled')<p><strong>CANCELLED: {{ $invoice->cancel_reason }}</strong></p>@endif
</body>
</html>
