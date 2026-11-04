<x-layouts.guest title="Account status">
    @if ($organization->status === \App\Enums\OrganizationStatus::Suspended)
        <h1 class="text-lg font-semibold text-slate-900">{{ $organization->name }} is suspended</h1>
        <p class="mt-2 text-sm text-slate-600">Your records are safe. {{ $organization->suspension_reason }}</p>
        <p class="mt-2 text-sm text-slate-600">Please contact support to restore access.</p>
    @else
        <h1 class="text-lg font-semibold text-slate-900">Your access has ended</h1>
        <p class="mt-2 text-sm text-slate-600">Your access ended on {{ $organization->access_until?->format('d M Y') }}. Your records are safe, and everything comes back as soon as your access is renewed.</p>
        <p class="mt-2 text-sm text-slate-600">Please contact us to renew.</p>
    @endif
    @if (config('plans.enabled') && $organization->suspension_reason === 'Subscription not paid')
        <form method="POST" action="{{ route('app.subscription.renew') }}" class="mt-4 space-y-2">
            @csrf
            <label class="label" for="plan_id">Choose a plan</label>
            <select class="input" id="plan_id" name="plan_id">@foreach (\App\Models\Plan::query()->where('is_active', true)->orderBy('price_paise')->get() as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select>
            <button class="btn btn-primary w-full">Renew</button>
        </form>
    @endif
    @if (auth('web')->user()?->memberships()->active()->count() > 1)
        <a class="btn btn-secondary mt-4 w-full" href="{{ route('app.organizations') }}">Switch to another practice</a>
    @endif
    <form method="POST" action="{{ route('app.logout') }}" class="mt-4">@csrf<button class="btn btn-secondary w-full">Sign out</button></form>
</x-layouts.guest>
