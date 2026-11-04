<x-layouts.app title="Audit log">
    <x-page-header title="Audit log">
        <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Action or person" aria-label="Search"></form>
        <a class="btn btn-secondary" href="{{ route('app.audit.export', request()->query()) }}">Export CSV</a>
    </x-page-header>
    @include('partials.audit-table', ['logs' => $logs])
</x-layouts.app>
