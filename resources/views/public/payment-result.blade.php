<x-layouts.guest :title="$paid ? 'Payment received' : 'Payment not confirmed'" portal="patient">
    @if ($paid)
        <h1 class="text-xl font-semibold text-slate-900">Thank you. Your payment was received.</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $organization->name }} will have your receipt ready.</p>
    @else
        <h1 class="text-xl font-semibold text-slate-900">We could not confirm this payment</h1>
        <p class="mt-2 text-sm text-slate-600">If money left your account, it will show up shortly. Otherwise please contact {{ $organization->name }}.</p>
    @endif
</x-layouts.guest>
