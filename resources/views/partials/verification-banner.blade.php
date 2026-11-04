@if ($organization->status === \App\Enums\OrganizationStatus::Rejected)
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
        <p class="font-semibold">Your verification was not approved</p>
        <p class="mt-1">{{ $organization->rejection_reason }}</p>
        @can('settings.manage')<a class="mt-2 inline-block font-medium underline" href="{{ route('app.verification.index') }}">Fix this and send it again</a>@endcan
    </div>
@else
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
        <p class="font-medium">Your account is under verification (usually within 24 hours)</p>
        @can('settings.manage')<a class="font-medium underline" href="{{ route('app.verification.index') }}">View what we need</a>@endcan
    </div>
@endif
