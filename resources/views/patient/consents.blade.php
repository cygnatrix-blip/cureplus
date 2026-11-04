<x-layouts.patient :title="__('Sharing')">
    <section class="card card-body">
        <h1 class="font-semibold text-slate-900">{{ __('Who can see your records') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __("Each clinic sees only its own records. A doctor sees another clinic's records only if you give them the code we text you.") }}</p>
    </section>
    <section class="card mt-4">
        @forelse ($consents as $consent)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                <span>{{ $consent->purpose === \App\Models\Consent::CROSS_ORG_HISTORY ? $consent->granteeDoctor?->name : __('Booking and care') }} · {{ $consent->granteeOrganization?->name }} · {{ $consent->granted_at->format('d M Y') }}
                    <span class="badge badge-slate">{{ $consent->revoked_at ? __('Stopped') : ($consent->isActive() ? __('Active') : __('Expired')) }}</span>
                </span>
                @if ($consent->isActive())
                    <form method="POST" action="{{ route('patient.consents.revoke', $consent->id) }}" data-confirm="{{ __('Stop sharing') }}?">@csrf<button class="btn btn-sm btn-danger">{{ __('Stop sharing') }}</button></form>
                @endif
            </div>
        @empty
            <x-empty :title="__('Nothing here yet.')" />
        @endforelse
    </section>
</x-layouts.patient>
