@php($money = \App\Support\Money::class)
<x-layouts.app title="Subscription">
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card card-body">
            <h2 class="font-semibold text-slate-900">Your plan</h2>
            @if ($subscription)
                <p class="mt-2 text-2xl font-semibold">{{ $subscription->plan?->name }}</p>
                <p class="text-sm"><span class="badge badge-slate">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span></p>
                @if ($subscription->status === 'trial')<p class="mt-2 text-sm text-slate-600">Trial ends {{ $subscription->trial_ends_at?->format('d M Y') }}</p>@endif
                @if ($subscription->grace_ends_at)<p class="mt-2 text-sm text-rose-700">Pay by {{ $subscription->grace_ends_at->format('d M Y') }} to keep your account active.</p>@endif
                @if ($subscription->current_period_ends_at && $subscription->status === 'active')<p class="mt-2 text-sm text-slate-600">Renews {{ $subscription->current_period_ends_at->format('d M Y') }}</p>@endif
                @if ($subscription->cancelled_at)<p class="mt-2 text-sm text-slate-600">Cancelled on {{ $subscription->cancelled_at->format('d M Y') }}</p>@endif
                @if ($subscription->status === 'active' && ! $subscription->cancelled_at)
                    <form method="POST" action="{{ route('app.subscription.cancel') }}" class="mt-3" data-confirm="Cancel your subscription at the end of this billing period?">@csrf<button class="btn btn-sm btn-secondary">Cancel subscription</button></form>
                @endif
            @else
                @if ($tenant->organization()?->isAwaitingApproval())<p class="mt-2 text-sm text-slate-600">Your account is under verification. Your free trial starts on the day you are approved.</p>@else<p class="mt-2 text-sm text-slate-600">Your free trial has not started yet. Please contact support.</p>@endif
            @endif
        </section>
        <section class="card card-body lg:col-span-2">
            <h2 class="font-semibold text-slate-900">Usage</h2>
            <dl class="mt-2 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                @foreach (['doctors' => 'Doctors', 'staff' => 'Team members', 'locations' => 'Locations', 'messages_per_month' => 'SMS/WhatsApp this month'] as $key => $label)
                    <div><dt class="text-slate-500">{{ $label }}</dt><dd class="text-lg font-semibold">{{ $usage[$key] }} <span class="text-sm font-normal text-slate-500">/ {{ $limits[$key] ?? 'Unlimited' }}</span></dd></div>
                @endforeach
            </dl>
        </section>
    </div>
    <h2 class="mt-6 mb-3 font-semibold text-slate-900">Plans</h2>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($plans as $plan)
            <form method="POST" action="{{ route('app.subscription.checkout') }}" class="card card-body flex flex-col gap-2">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <h3 class="text-lg font-semibold text-slate-900">{{ $plan->name }}</h3>
                <p class="text-2xl font-semibold">{{ $money::format($plan->price_paise) }}<span class="text-sm font-normal text-slate-500"> / {{ $plan->billing_interval }}</span></p>
                <ul class="flex-1 text-sm text-slate-600">
                    <li>{{ $plan->limits['doctors'] ?? 'Unlimited' }} {{ \Illuminate\Support\Str::plural('doctor', $plan->limits['doctors'] ?? 2) }}</li>
                    <li>{{ $plan->limits['staff'] ?? 'Unlimited' }} {{ \Illuminate\Support\Str::plural('team member', $plan->limits['staff'] ?? 2) }}</li>
                    <li>{{ $plan->limits['locations'] ?? 'Unlimited' }} {{ \Illuminate\Support\Str::plural('location', $plan->limits['locations'] ?? 2) }}</li>
                    <li>{{ $plan->limits['messages_per_month'] ?? 'Unlimited' }} {{ \Illuminate\Support\Str::plural('message', $plan->limits['messages_per_month'] ?? 2) }} a month</li>
                </ul>
                <input type="hidden" name="coupon" value="{{ session('applied_coupon') }}">
                <button class="btn btn-primary">Choose {{ $plan->name }}</button>
            </form>
        @endforeach
    </div>
    <form method="POST" action="{{ route('app.subscription.coupon') }}" class="card card-body mt-4 flex flex-wrap items-end gap-2">
        @csrf
        <x-field name="code" label="Have a coupon?" />
        <button class="btn btn-secondary">Apply</button>
    </form>
    <section class="card mt-4">
        <div class="card-header"><h2 class="card-title">Invoices</h2></div>
        @forelse ($invoices as $invoice)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5">
                <span>{{ $invoice->number }} · {{ $invoice->paid_at?->format('d M Y') }} · {{ $money::format($invoice->amount_paise) }}</span>
                <a class="btn btn-sm btn-secondary" href="{{ route('app.subscription.invoice', $invoice) }}">PDF</a>
            </div>
        @empty
            <x-empty title="No invoices yet" />
        @endforelse
    </section>
</x-layouts.app>
