<x-layouts.admin title="IT admins">
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>2FA</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($admins as $admin)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $admin->name }}</td>
                        <td>{{ $admin->email }}</td>
                        <td>{{ $admin->roleName() }}</td>
                        <td>{{ $admin->hasTwoFactorEnabled() ? 'On' : 'Off' }}</td>
                        <td><x-org-status :status="$admin->status" /></td>
                        <td class="text-right">
                            @if ($admin->isActive() && ! $admin->is(auth('platform')->user()))
                                <form method="POST" action="{{ route('admin.admins.reset', $admin) }}" class="mb-1" data-confirm="Reset {{ $admin->name }}'s password and 2FA?">@csrf<button class="btn btn-sm btn-secondary">Reset password</button></form>
                                <form method="POST" action="{{ route('admin.admins.deactivate', $admin) }}" data-confirm="Deactivate {{ $admin->name }}?">@csrf<button class="btn btn-sm btn-secondary">Deactivate</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="mt-4">{{ $admins->links() }}</div>

    <form method="POST" action="{{ route('admin.admins.store') }}" class="card card-body mt-4 max-w-2xl space-y-4">
        @csrf
        <h3 class="font-semibold text-slate-900">Add an admin</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Name" required />
            <x-field name="email" label="Email" type="email" required />
        </div>
        <div>
            <label for="role" class="label">Role</label>
            <select id="role" name="role" class="input">
                @foreach ($roles as $key => $role)<option value="{{ $key }}" @selected(old('role') === $key)>{{ $role['name'] }}</option>@endforeach
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="password" label="Temporary password" type="password" autocomplete="new-password" required />
            <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        </div>
        <button class="btn btn-primary">Add admin</button>
    </form>
</x-layouts.admin>
