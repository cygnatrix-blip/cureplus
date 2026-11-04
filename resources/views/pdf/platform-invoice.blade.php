<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #ccc; padding: 6px; text-align: left; }
    </style>
</head>
<body>
    <h2>{{ config('app.name') }}</h2>
    <p><strong>Invoice {{ $invoice->number }}</strong><br>{{ $invoice->paid_at?->format('d M Y') }}</p>
    <p>Billed to: {{ $organization->name }}@if ($organization->gstin)<br>GSTIN: {{ $organization->gstin }}@endif</p>
    <table>
        <tr><th>Description</th><th>Amount</th></tr>
        <tr><td>{{ $invoice->plan?->name }} plan subscription</td><td>{{ \App\Support\Money::format($invoice->amount_paise) }}</td></tr>
    </table>
    <p>Paid by Razorpay ({{ $invoice->razorpay_payment_id }}).</p>
</body>
</html>
