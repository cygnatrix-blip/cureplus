@php($money = \App\Support\Money::class)
<x-layouts.app :title="$invoice->number">
    <div class="mb-4 flex flex-wrap justify-end gap-2 print:hidden">
        <button type="button" class="btn btn-primary" data-print>Print</button>
        <a class="btn btn-secondary" href="{{ route('app.invoices.pdf', $invoice) }}">Download PDF</a>
        <form method="POST" action="{{ route('app.invoices.email', $invoice) }}">@csrf<button class="btn btn-secondary">Email</button></form>
        <form method="POST" action="{{ route('app.invoices.share', $invoice) }}">@csrf<button class="btn btn-secondary">Send to patient</button></form>
        <a class="btn btn-ghost" href="{{ route('app.invoices.index') }}">Back</a>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <article class="card card-body lg:col-span-2 print:col-span-3 print:border-0 print:shadow-none">
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
        </article>
        <aside class="space-y-4 print:hidden">
            @can('update', $invoice)
                @if ($invoice->duePaise() > 0)
                    <form method="POST" action="{{ route('app.invoices.pay', $invoice) }}" class="card card-body space-y-3">
                        @csrf
                        <h3 class="font-semibold text-slate-900">Take payment</h3>
                        <select class="input" name="mode" aria-label="Payment mode"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option></select>
                        <x-field name="amount" label="Amount (₹)" inputmode="decimal" :value="$money::decimal($invoice->duePaise())" required />
                        <x-field name="reference" label="Reference (optional)" />
                        <button class="btn btn-primary w-full">Record payment</button>
                    </form>
                    @if ($onlineEnabled)
                        <form method="POST" action="{{ route('app.invoices.payment-link', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Send online payment link</button></form>
                    @endif
                @endif
            @endcan
            <section class="card">
                <div class="card-header"><h3 class="card-title">Payments</h3></div>
                @forelse ($invoice->payments as $payment)
                    <div class="border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                        <div class="flex justify-between gap-2"><span>{{ \App\Models\Payment::MODES[$payment->mode] ?? $payment->mode }} · {{ ucfirst($payment->status) }}</span><span class="font-medium">{{ $money::format($payment->amount_paise) }}</span></div>
                        <p class="text-xs text-slate-500">{{ $payment->paid_at?->format('d M Y H:i') }} {{ $payment->reference }}</p>
                        @can('refund', $invoice)
                            @if ($payment->status === 'captured' && $payment->amount_paise > $payment->refundedPaise())
                                <form method="POST" action="{{ route('app.invoices.refund', $invoice) }}" class="mt-2 space-y-1" data-confirm="Refund this amount?">
                                    @csrf
                                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                    <input class="input" name="amount" inputmode="decimal" value="{{ $money::decimal($payment->amount_paise - $payment->refundedPaise()) }}" aria-label="Refund amount" required>
                                    <input class="input" name="reason" placeholder="Reason" aria-label="Refund reason" required>
                                    <button class="btn btn-sm btn-secondary">Refund</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                @empty
                    <x-empty title="No payments yet" />
                @endforelse
            </section>
            @if ($invoice->refunds->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h3 class="card-title">Refunds</h3></div>
                    @foreach ($invoice->refunds as $refund)
                        <div class="border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">{{ $money::format($refund->amount_paise) }} · {{ $refund->reason }} · {{ $refund->refunded_at->format('d M Y') }}</div>
                    @endforeach
                </section>
            @endif
            @can('refund', $invoice)
                @if ($invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route('app.invoices.cancel', $invoice) }}" class="card card-body space-y-2" data-confirm="Cancel this bill?">
                        @csrf
                        <x-field name="reason" label="Reason for cancelling" required />
                        <button class="btn btn-danger w-full">Cancel bill</button>
                    </form>
                @endif
            @endcan
        </aside>
    </div>
</x-layouts.app>
