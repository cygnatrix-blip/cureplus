<x-layouts.app title="Staff and doctors">
    <x-page-header title="Staff and doctors">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="search" name="q" value="{{ $search }}" placeholder="Name, email or mobile" aria-label="Search">
            <select class="input w-auto" name="status" data-auto-submit aria-label="Status">
                <option value="">All</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
        </form>
        <a class="btn btn-primary" href="{{ route('app.staff.index', ['invite' => 'doctor']) }}#invite">Add a doctor</a>
    </x-page-header>

    <div class="card">
        @if ($memberships->isEmpty())
            <x-empty title="No staff found" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th>Contact</th><th>Roles</th><th>Locations</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($memberships as $membership)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $membership->user->name }}</td>
                            <td>{{ $membership->user->email ?? $membership->user->mobile }}</td>
                            <td>{{ $membership->roles->pluck('name')->join(', ') }}</td>
                            <td>{{ $membership->hasRole('owner') ? 'All' : $membership->locations->pluck('name')->join(', ') }}</td>
                            <td><x-org-status :status="$membership->status" /></td>
                            <td class="text-right"><a class="btn btn-sm btn-secondary" href="{{ route('app.staff.edit', $membership) }}">Manage</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $memberships->links() }}</div>

    @php
        $checkedRoles = old('role_keys', request('invite') === 'doctor' ? ['doctor'] : []);
        $checkedLocations = old('location_ids', $locations->count() === 1 ? [$locations->first()->id] : []);
    @endphp
    <section id="invite" class="card card-body mt-4 scroll-mt-20">
        <h3 class="mb-1 font-semibold text-slate-900">{{ request('invite') === 'doctor' ? 'Add a doctor' : 'Invite someone' }}</h3>
        <details class="mb-3 text-sm text-slate-600" @if (request('invite') === 'doctor') open @endif>
            <summary class="cursor-pointer font-medium text-brand-700">How adding a doctor works</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Enter their email, tick Doctor, and choose where they sit.</li>
                <li>They get an email link, and set their name and password. If they already use CurePlus elsewhere, they just sign in.</li>
                <li>They add their qualification and registration number under My account.</li>
                <li>You set their hours under <a class="underline" href="{{ route('app.calendar.index') }}">Doctor calendar</a> and their fee under <a class="underline" href="{{ route('app.billing-settings.index') }}">Fees and services</a>.</li>
                <li>They now appear on your booking page, with their own link under <a class="underline" href="{{ route('app.booking-links.index') }}">Booking links</a>.</li>
            </ol>
            <p class="mt-2">If you see patients yourself, press Manage on your own row above and tick Doctor.</p>
        </details>
        <form method="POST" action="{{ route('app.invitations.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Name (optional)" />
                <x-field name="email" label="Email" type="email" required />
            </div>
            <fieldset>
                <legend class="label">Roles</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="role_keys[]" value="{{ $role->key }}" @checked(in_array($role->key, $checkedRoles, true))> {{ $role->name }}</label>
                    @endforeach
                </div>
                @error('role_keys')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>
            <fieldset>
                <legend class="label">Locations</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($locations as $location)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="location_ids[]" value="{{ $location->id }}" @checked(in_array($location->id, $checkedLocations))> {{ $location->name }}</label>
                    @endforeach
                </div>
                @error('location_ids')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>
            <button class="btn btn-primary">Send invitation</button>
        </form>
    </section>

    @if ($invitations->isNotEmpty())
        <section class="card mt-4">
            <div class="card-header"><h3 class="card-title">Pending invitations</h3></div>
            @foreach ($invitations as $invitation)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                    <span class="min-w-0 truncate">{{ $invitation->email }} · {{ implode(', ', array_map('ucfirst', $invitation->role_keys)) }} · expires {{ $invitation->expires_at->diffForHumans() }}</span>
                    <form method="POST" action="{{ route('app.invitations.destroy', $invitation) }}" data-confirm="Cancel this invitation?">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Cancel</button></form>
                </div>
            @endforeach
        </section>
    @endif
</x-layouts.app>
