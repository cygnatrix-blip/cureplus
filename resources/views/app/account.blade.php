<x-layouts.app title="My account">
    <div class="mx-auto max-w-3xl space-y-4">
        <section class="card card-body">
            <h2 class="font-semibold text-slate-900">Profile</h2>
            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Name</dt><dd>{{ $user->name }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd>{{ $user->email ?? 'Not set' }} @if ($user->email_verified_at)<span class="badge badge-green">Verified</span>@endif</dd></div>
                <div><dt class="text-slate-500">Mobile</dt><dd>{{ $user->mobile ?? 'Not set' }}</dd></div>
                <div><dt class="text-slate-500">Two-factor sign-in</dt><dd>{{ $user->hasTwoFactorEnabled() ? 'On' : 'Off' }} @unless ($user->hasTwoFactorEnabled())<a class="text-brand-700 underline" href="{{ route('app.two-factor.setup') }}">Turn on</a>@endunless</dd></div>
            </dl>
        </section>

        @if ($isDoctor)
            <form method="POST" action="{{ route('app.doctor-profile.update') }}" class="card card-body space-y-3">
                @csrf
                <div class="flex items-center justify-between gap-2"><h2 class="font-semibold text-slate-900">Doctor details</h2><span class="badge badge-slate">{{ ucfirst($doctorProfile?->verification_status ?? 'unverified') }}</span></div>
                <p class="text-sm text-slate-600">These print on your prescriptions.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="qualification" label="Qualification" :value="$doctorProfile?->qualification" />
                    <x-field name="specialty" label="Specialty" :value="$doctorProfile?->specialty" />
                    <x-field name="registration_number" label="Registration number" :value="$doctorProfile?->registration_number" />
                    <x-field name="registration_council" label="Medical council" :value="$doctorProfile?->registration_council" />
                </div>
                <button class="btn btn-secondary">Save doctor details</button>
            </form>
        @endif

        <section class="card card-body space-y-2">
            <h2 class="font-semibold text-slate-900">Notifications on this device</h2>
            @include('partials.push-toggle', ['subscribeUrl' => route('app.push.subscribe'), 'unsubscribeUrl' => route('app.push.unsubscribe')])
        </section>

        <section class="card card-body">
            <h2 class="mb-3 font-semibold text-slate-900">Change password</h2>
            <form method="POST" action="{{ route('app.account.password') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="current_password" label="Current password" type="password" autocomplete="current-password" required />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="password" label="New password" type="password" autocomplete="new-password" required />
                    <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                </div>
                <button class="btn btn-primary">Update password</button>
            </form>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Signed-in devices</h2></div>
            @forelse ($sessions as $session)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:px-5">
                    <div class="min-w-0 text-sm">
                        <p class="truncate text-slate-900">{{ \Illuminate\Support\Str::limit($session->user_agent, 70) }}</p>
                        <p class="text-slate-500">{{ $session->ip_address }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</p>
                    </div>
                    @if ($session->is_current)
                        <span class="badge badge-green">This device</span>
                    @else
                        <form method="POST" action="{{ route('app.account.sessions.revoke', $session->token) }}" data-confirm="Sign out this device?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-secondary">Sign out</button>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty title="No other devices" />
            @endforelse
        </section>
    </div>
</x-layouts.app>
