@php($noun = $tenant->organization()->type->locationNoun())
<x-layouts.app :title="$noun.'es'">
    <x-page-header :title="$tenant->organization()->isSolo() ? 'Chambers' : 'Branches'">
        <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Search" aria-label="Search"></form>
        <a class="btn btn-primary" href="{{ route('app.locations.create') }}">Add {{ strtolower($noun) }}</a>
    </x-page-header>
    <div class="card">
        @if ($locations->isEmpty())
            <x-empty :title="'No '.strtolower($noun).' found'" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th>City</th><th>Phone</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($locations as $location)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $location->name }}</td>
                            <td>{{ $location->city }}</td>
                            <td>{{ $location->phone }}</td>
                            <td><x-org-status :status="$location->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-right"><a class="btn btn-sm btn-secondary" href="{{ route('app.locations.edit', $location) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $locations->links() }}</div>
</x-layouts.app>
