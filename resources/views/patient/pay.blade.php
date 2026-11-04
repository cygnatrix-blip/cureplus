<x-layouts.patient :title="__('Pay online')">
    <section class="card card-body text-center">
        <p class="text-sm text-slate-600">{{ $invoice->number }}</p>
        <p class="my-3 text-3xl font-semibold text-slate-900">{{ \App\Support\Money::format($payment->amount_paise) }}</p>
        <a class="btn btn-primary w-full" href="{{ $payment->gateway_link_url }}" rel="noopener">{{ __('Continue to payment') }}</a>
        <p class="mt-3 text-xs text-slate-500">{{ __("You will finish paying on Razorpay's secure page.") }}</p>
    </section>
</x-layouts.patient>
