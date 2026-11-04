<x-layouts.app title="Role permissions">
    <x-page-header title="Role permissions" subtitle="Owners always have every permission." />
    <form method="POST" action="{{ route('app.roles.update') }}" class="card">
        @csrf @method('PUT')
        @foreach ($roles as $role)<input type="hidden" name="grants[{{ $role->key }}][]" value="">@endforeach
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Permission</th>@foreach ($roles as $role)<th class="text-center">{{ $role->name }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($permissions as $group => $items)
                    <tr><td colspan="{{ $roles->count() + 1 }}" class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase">{{ $group }}</td></tr>
                    @foreach ($items as $permission)
                        <tr>
                            <td>{{ $permission->label }}</td>
                            @foreach ($roles as $role)
                                <td class="text-center"><input type="checkbox" name="grants[{{ $role->key }}][]" value="{{ $permission->key }}" aria-label="{{ $role->name }}: {{ $permission->label }}" @checked(in_array($permission->key, $matrix[$role->key] ?? [], true))></td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table></div>
        <div class="p-4 text-right"><button class="btn btn-primary">Save permissions</button></div>
    </form>
</x-layouts.app>
