@php($money = \App\Support\Money::class)
<x-layouts.admin title="Plans and subscriptions">
    <section class="grid gap-4 lg:grid-cols-3">
        @foreach ($plans as $plan)
            <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="card card-body space-y-2">
                @csrf @method('PUT')
                <p class="text-xs text-slate-500">{{ $plan->code }}</p>
                @include('admin.plans.fields', ['plan' => $plan, 'suffix' => $plan->id])
                <button class="btn btn-secondary">Save</button>
            </form>
        @endforeach
    </section>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.plans.store') }}" class="card card-body space-y-2">
            @csrf
            <h3 class="font-semibold text-slate-900">New plan</h3>
            <x-field name="code" label="Code" required />
            @include('admin.plans.fields', ['plan' => new \App\Models\Plan(['billing_interval' => 'month', 'trial_days' => 14, 'limits' => [], 'features' => [], 'is_active' => true]), 'suffix' => 'new'])
            <button class="btn btn-primary">Create plan</button>
        </form>
        <section class="card card-body space-y-3">
            <h3 class="font-semibold text-slate-900">Coupons</h3>
            <form method="POST" action="{{ route('admin.coupons.store') }}" class="grid gap-2 sm:grid-cols-2">
                @csrf
                <x-field name="code" label="Code" required />
                <x-field name="extra_trial_days" label="Extra trial days" type="number" min="0" />
                <x-field name="razorpay_offer_id" label="Razorpay offer ID" />
                <x-field name="max_redemptions" label="Maximum uses" type="number" min="1" />
                <x-field name="expires_at" label="Expires" type="date" />
                <div class="flex items-end"><button class="btn btn-secondary">Add coupon</button></div>
            </form>
            <ul class="text-sm">
                @foreach ($coupons as $coupon)<li>{{ $coupon->code }} · used {{ $coupon->redeemed_count }}{{ $coupon->max_redemptions ? ' of '.$coupon->max_redemptions : '' }} · {{ $coupon->isRedeemable() ? 'Active' : 'Ended' }}</li>@endforeach
            </ul>
        </section>
    </div>
    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Subscriptions</h3></div>
        @if ($subscriptions->isEmpty())
            <x-empty title="No subscriptions yet" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Organization</th><th>Plan</th><th>Status</th><th>Trial or period ends</th><th></th></tr></thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td>{{ $subscription->organization?->name }}</td>
                            <td>{{ $subscription->plan?->name }}</td>
                            <td><span class="badge badge-slate">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span></td>
                            <td>{{ ($subscription->current_period_ends_at ?? $subscription->trial_ends_at)?->format('d M Y') }}</td>
                            <td class="text-right">
                                @if ($subscription->organization)
                                    <form method="POST" action="{{ route('admin.subscriptions.change-plan', $subscription->organization) }}" class="flex justify-end gap-1">
                                        @csrf
                                        <select class="input min-h-9 w-auto py-1" name="plan_id" aria-label="Plan">@foreach ($plans as $plan)<option value="{{ $plan->id }}" @selected($plan->id === $subscription->plan_id)>{{ $plan->name }}</option>@endforeach</select>
                                        <button class="btn btn-sm btn-secondary">Change</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
</x-layouts.admin>
