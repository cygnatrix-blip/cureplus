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
        @if ($membership->hasRole('doctor'))
            <form method="POST" action="{{ route('app.staff.photo', $membership) }}" enctype="multipart/form-data" class="card card-body space-y-2" x-data="photoInput">
                @csrf
                <h2 class="font-semibold text-slate-900">Doctor's photo</h2>
                <img x-show="hasPreview" :src="preview" alt="" class="h-24 w-24 rounded-full object-cover">
                <input class="input" type="file" name="photo" accept="image/*" required @change="shrink" aria-label="Doctor's photo">
                <p class="hint">Shown on your website. Ask the doctor first, and use a clear, recent photo.</p>
                <p class="field-error" x-show="error" x-text="error" role="alert"></p>
                @error('photo')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn btn-secondary" :disabled="busy">Save photo</button>
            </form>
        @endif
        @if ($membership->isActive())
            <form method="POST" action="{{ route('app.staff.deactivate', $membership) }}" class="text-right" data-confirm="Remove {{ $membership->user->name }}'s access?">@csrf<button class="btn btn-danger">Remove access</button></form>
        @else
            <form method="POST" action="{{ route('app.staff.activate', $membership) }}" class="text-right">@csrf<button class="btn btn-secondary">Restore access</button></form>
        @endif
    </div>
</x-layouts.app>
