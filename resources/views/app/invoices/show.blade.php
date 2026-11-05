@php($money = \App\Support\Money::class)
<x-layouts.app :title="$invoice->number">
    <div class="mb-3 flex flex-wrap items-center justify-end gap-2 print:hidden">
        <x-send-to-patient labelled :patient="$invoice->patient" event="document_ready" :link="$shareLink" :variables="['name' => $invoice->patient->name, 'document' => 'bill', 'clinic' => $organization->name, 'link' => $shareLink]" />
    </div>
    <div class="mb-4 flex flex-wrap justify-end gap-2 print:hidden">
        <button type="button" class="btn btn-primary" data-print>Print</button>
        <a class="btn btn-secondary" href="{{ route('app.invoices.pdf', $invoice) }}">Download PDF</a>
        <form method="POST" action="{{ route('app.invoices.email', $invoice) }}">@csrf<button class="btn btn-secondary">Email</button></form>
        <form method="POST" action="{{ route('app.invoices.share', $invoice) }}">@csrf<button class="btn btn-secondary">Send automatically</button></form>
        <a class="btn btn-ghost" href="{{ route('app.invoices.index') }}">Back</a>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <article class="card card-body lg:col-span-2 print:col-span-3 print:border-0 print:shadow-none">
            @include('partials.invoice-document')
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
                        @if ($payment->status === 'pending' && $payment->gateway_link_url)
                            <div class="mt-1"><x-send-to-patient :patient="$invoice->patient" event="payment_link" :link="$payment->gateway_link_url" :variables="['name' => $invoice->patient->name, 'amount' => $money::format($payment->amount_paise), 'clinic' => $organization->name, 'link' => $payment->gateway_link_url]" /></div>
                        @endif
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
