<x-layouts.admin :title="$organization->name">
    <x-page-header :title="$organization->name" :subtitle="$organization->type->label().' · /'.$organization->slug">
        <x-org-status :status="$organization->status" />
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        @can('platform.organizations.manage')
            <section class="card card-body space-y-3">
                <h3 class="font-semibold text-slate-900">Status</h3>
                @if ($organization->suspension_reason)<p class="text-sm text-slate-600">Suspended: {{ $organization->suspension_reason }}</p>@endif
                @can('platform.approvals.manage')
                    @if ($organization->isAwaitingApproval())
                        <a class="btn btn-primary" href="{{ route('admin.approvals.show', $organization) }}">Review documents</a>
                    @elseif ($organization->status === \App\Enums\OrganizationStatus::Suspended)
                        <form method="POST" action="{{ route('admin.organizations.approve', $organization) }}">@csrf<button class="btn btn-primary">Restore access</button></form>
                    @endif
                @endcan
                @if ($organization->status !== \App\Enums\OrganizationStatus::Suspended)
                    <form method="POST" action="{{ route('admin.organizations.suspend', $organization) }}" class="space-y-2" data-confirm="Suspend {{ $organization->name }}? Their staff lose access right away.">
                        @csrf
                        <x-field name="reason" label="Reason for suspending" required />
                        <button class="btn btn-danger">Suspend</button>
                    </form>
                @endif
            </section>
        @endcan
        @can('platform.impersonate')
            <section class="card card-body space-y-3">
                <h3 class="font-semibold text-slate-900">View as owner</h3>
                <p class="text-sm text-slate-600">Recorded in the audit log. The owner's password and 2FA cannot be changed while viewing.</p>
                <form method="POST" action="{{ route('admin.organizations.impersonate', $organization) }}" class="space-y-2">
                    @csrf
                    <x-field name="reason" label="Why do you need to view this account?" required />
                    <button class="btn btn-secondary">View as owner</button>
                </form>
            </section>
        @endcan
    </div>

    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Members</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Contact</th><th>Roles</th><th>2FA</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($memberships as $membership)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $membership->user?->name }}</td>
                        <td>{{ \App\Support\Mask::identifier($membership->user?->email ?? $membership->user?->mobile) }}</td>
                        <td>{{ $membership->roles->pluck('name')->join(', ') }}</td>
                        <td>{{ $membership->user?->two_factor_confirmed_at ? 'On' : 'Off' }}</td>
                        <td><x-org-status :status="$membership->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>
    <div class="mt-4">{{ $memberships->links() }}</div>

    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Locations</h3></div>
        @forelse ($locations as $location)
            <div class="border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">{{ $location->name }}@if ($location->city) · {{ $location->city }}@endif</div>
        @empty
            <x-empty title="No locations" />
        @endforelse
    </section>
</x-layouts.admin>
