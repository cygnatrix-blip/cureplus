<x-layouts.guest title="Account status">
    @if ($organization->status === \App\Enums\OrganizationStatus::Pending)
        <h1 class="text-lg font-semibold text-slate-900">Your registration is being reviewed</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $organization->name }} will be activated after our team approves it. We will let you know by email.</p>
    @else
        <h1 class="text-lg font-semibold text-slate-900">{{ $organization->name }} is suspended</h1>
        <p class="mt-2 text-sm text-slate-600">Your records are safe. {{ $organization->suspension_reason }}</p>
        @if ($organization->suspension_reason === 'Subscription not paid')
            <form method="POST" action="{{ route('app.subscription.renew') }}" class="mt-4 space-y-2">
                @csrf
                <label class="label" for="plan_id">Choose a plan</label>
                <select class="input" id="plan_id" name="plan_id">@foreach (\App\Models\Plan::query()->where('is_active', true)->orderBy('price_paise')->get() as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select>
                <button class="btn btn-primary w-full">Renew</button>
            </form>
        @endif
    @endif
    <form method="POST" action="{{ route('app.logout') }}" class="mt-4">@csrf<button class="btn btn-secondary w-full">Sign out</button></form>
</x-layouts.guest>
