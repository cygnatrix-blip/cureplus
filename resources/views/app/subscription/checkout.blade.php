<x-layouts.guest title="Continue to payment">
    <h1 class="text-lg font-semibold text-slate-900">{{ $plan->name }} plan</h1>
    <p class="mt-2 text-2xl font-semibold">{{ \App\Support\Money::format($plan->price_paise) }} / {{ $plan->billing_interval }}</p>
    <a class="btn btn-primary mt-4 w-full" href="{{ $subscription->checkout_url }}" rel="noopener">Continue to Razorpay</a>
    <p class="mt-3 text-xs text-slate-500">Your plan starts when Razorpay confirms the payment. This can take a minute.</p>
    <a class="btn btn-ghost mt-2 w-full" href="{{ route('app.dashboard') }}">Back</a>
</x-layouts.guest>
