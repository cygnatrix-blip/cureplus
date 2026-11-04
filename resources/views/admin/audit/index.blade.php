<x-layouts.admin title="Audit log">
    <x-page-header title="Platform audit log">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="search" name="q" value="{{ $search }}" placeholder="Action or person" aria-label="Search">
            <select class="input w-auto" name="actor" data-auto-submit aria-label="Who">
                <option value="">Everyone</option>
                <option value="user" @selected($actorType === 'user')>Clinic users</option>
                <option value="platform_admin" @selected($actorType === 'platform_admin')>Platform admins</option>
            </select>
        </form>
        <a class="btn btn-secondary" href="{{ route('admin.audit.export', request()->query()) }}">Export CSV</a>
    </x-page-header>
    @include('partials.audit-table', ['logs' => $logs])
</x-layouts.admin>
