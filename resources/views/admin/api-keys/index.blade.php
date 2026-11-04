<x-layouts.admin title="API keys">
    @if (session('new_api_key'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
            <p class="font-semibold">Copy this key now. It will not be shown again.</p>
            <p class="mt-1 font-mono break-all">{{ session('new_api_key') }}</p>
        </div>
    @endif
    <form method="POST" action="{{ route('admin.api-keys.store') }}" class="card card-body max-w-2xl space-y-3">
        @csrf
        <h2 class="font-semibold text-slate-900">Issue a key</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-field name="name" label="Name" required />
            <x-field name="organization_slug" label="Organization link name (optional)" />
            <x-field name="expires_at" label="Expires" type="date" />
        </div>
        <fieldset>
            <legend class="label">Permissions</legend>
            @foreach ($scopes as $scope => $label)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="scopes[]" value="{{ $scope }}"> {{ $label }}</label>
            @endforeach
            @error('scopes')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <button class="btn btn-primary">Issue key</button>
    </form>
    <section class="card mt-4">
        @if ($keys->isEmpty())
            <x-empty title="No keys yet" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th>Key</th><th>Organization</th><th>Permissions</th><th>Last used</th><th></th></tr></thead>
                <tbody>
                    @foreach ($keys as $key)
                        <tr>
                            <td class="font-medium">{{ $key->name }}</td>
                            <td class="font-mono text-xs">{{ $key->prefix }}…</td>
                            <td>{{ $key->organization?->name ?? 'Platform' }}</td>
                            <td class="text-xs">{{ implode(', ', $key->scopes) }}</td>
                            <td class="whitespace-nowrap">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-right">
                                @if ($key->isUsable())
                                    <form method="POST" action="{{ route('admin.api-keys.revoke', $key->id) }}" data-confirm="Revoke {{ $key->name }}?">@csrf<button class="btn btn-sm btn-secondary">Revoke</button></form>
                                @else
                                    <span class="badge badge-red">{{ $key->revoked_at ? 'Revoked' : 'Expired' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $keys->links() }}</div>
</x-layouts.admin>
