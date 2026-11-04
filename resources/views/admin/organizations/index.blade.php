<x-layouts.admin title="Organizations">
    <x-page-header title="Organizations">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="search" name="q" value="{{ $search }}" placeholder="Name or link" aria-label="Search">
            <select class="input w-auto" name="status" data-auto-submit aria-label="Status">
                <option value="">All statuses</option>
                @foreach (\App\Enums\OrganizationStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ ucfirst($case->value) }}</option>
                @endforeach
            </select>
        </form>
        <a class="btn btn-secondary" href="{{ route('admin.organizations.export', request()->query()) }}">Export CSV</a>
    </x-page-header>
    <div class="card">
        @if ($organizations->isEmpty())
            <x-empty title="No organizations found" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th>Type</th><th>Members</th><th>Locations</th><th>Status</th><th>Created</th><th></th></tr></thead>
                <tbody>
                    @foreach ($organizations as $organization)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $organization->name }}<div class="text-xs font-normal text-slate-500">/{{ $organization->slug }}</div></td>
                            <td>{{ $organization->type->label() }}</td>
                            <td>{{ $organization->memberships_count }}</td>
                            <td>{{ $organization->locations_count }}</td>
                            <td><x-org-status :status="$organization->status" /></td>
                            <td class="whitespace-nowrap">{{ $organization->created_at?->format('d M Y') }}</td>
                            <td class="text-right"><a class="btn btn-sm btn-secondary" href="{{ route('admin.organizations.show', $organization) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $organizations->links() }}</div>
</x-layouts.admin>
