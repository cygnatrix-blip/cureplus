<x-layouts.app title="Manage access">
    <div class="mx-auto max-w-2xl space-y-4">
        <form method="POST" action="{{ route('app.staff.update', $membership) }}" class="card card-body space-y-4">
            @csrf @method('PUT')
            <div>
                <p class="font-semibold text-slate-900">{{ $membership->user->name }}</p>
                <p class="text-sm text-slate-500">{{ $membership->user->email ?? $membership->user->mobile }}</p>
            </div>
            <fieldset>
                <legend class="label">Roles</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role->key }}" @checked($membership->hasRole($role->key))> {{ $role->name }}</label>
                    @endforeach
                </div>
                @error('roles')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>
            <fieldset>
                <legend class="label">Locations</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($locations as $location)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="locations[]" value="{{ $location->id }}" @checked($membership->locations->contains('id', $location->id))> {{ $location->name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex justify-end gap-2"><a class="btn btn-ghost" href="{{ route('app.staff.index') }}">Back</a><button class="btn btn-primary">Save</button></div>
        </form>
        @if ($membership->isActive())
            <form method="POST" action="{{ route('app.staff.deactivate', $membership) }}" class="text-right" data-confirm="Remove {{ $membership->user->name }}'s access?">@csrf<button class="btn btn-danger">Remove access</button></form>
        @else
            <form method="POST" action="{{ route('app.staff.activate', $membership) }}" class="text-right">@csrf<button class="btn btn-secondary">Restore access</button></form>
        @endif
    </div>
</x-layouts.app>
