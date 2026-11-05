    <header class="flex flex-wrap justify-between gap-2 border-b border-slate-200 pb-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $organization->name }}</h2>
            <p class="text-sm text-slate-500">{{ $invoice->location->name }} {{ $invoice->location->city }}</p>
            @if ($invoice->gstin_snapshot)<p class="text-sm text-slate-500">GSTIN {{ $invoice->gstin_snapshot }}</p>@endif
        </div>
        <div class="text-right text-sm">
            <p class="font-semibold">{{ $invoice->isGstInvoice() ? 'Tax invoice' : 'Receipt' }}</p>
            <p class="font-mono">{{ $invoice->number }}</p>
            <p>{{ $invoice->issued_at->format('d M Y') }}</p>
        </div>
    </header>
    <p class="mt-3 text-sm">{{ $invoice->patient->name }} · {{ $invoice->patient->patient_code }} @if ($invoice->doctor)· {{ $invoice->doctor->name }}@endif</p>
    <div class="table-wrap mt-3"><table class="table">
        <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Rate</th>@if ($invoice->isGstInvoice())<th class="text-right">GST</th>@endif<th class="text-right">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ $money::format($item->unit_paise) }}</td>
                    @if ($invoice->isGstInvoice())<td class="text-right">{{ $item->gst_rate_bp / 100 }}%</td>@endif
                    <td class="text-right">{{ $money::format($item->line_total_paise) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
    <dl class="mt-3 ml-auto max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ $money::format($invoice->subtotal_paise) }}</dd></div>
        @if ($invoice->discount_paise)<div class="flex justify-between gap-3"><dt>Discount ({{ $invoice->discount_reason }})</dt><dd>-{{ $money::format($invoice->discount_paise) }}</dd></div>@endif
        @if ($invoice->tax_paise)
            <div class="flex justify-between"><dt>CGST</dt><dd>{{ $money::format((int) floor($invoice->tax_paise / 2)) }}</dd></div>
            <div class="flex justify-between"><dt>SGST</dt><dd>{{ $money::format((int) ceil($invoice->tax_paise / 2)) }}</dd></div>
        @endif
        <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{{ $money::format($invoice->total_paise) }}</dd></div>
        <div class="flex justify-between"><dt>Paid</dt><dd>{{ $money::format($invoice->paid_paise) }}</dd></div>
        @if ($invoice->refunded_paise)<div class="flex justify-between"><dt>Refunded</dt><dd>{{ $money::format($invoice->refunded_paise) }}</dd></div>@endif
        @if ($invoice->duePaise())<div class="flex justify-between font-semibold text-rose-700"><dt>Due</dt><dd>{{ $money::format($invoice->duePaise()) }}</dd></div>@endif
    </dl>
    @if ($invoice->status === 'cancelled')<p class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-900">Cancelled: {{ $invoice->cancel_reason }}</p>@endif
