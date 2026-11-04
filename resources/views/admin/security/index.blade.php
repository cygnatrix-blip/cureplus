<x-layouts.admin title="Security center">
    <div class="grid gap-3 sm:grid-cols-4">
        <x-stat label="Clinic sessions" :value="$activeUserSessions" />
        <x-stat label="Admin sessions" :value="$activeAdminSessions" />
        <x-stat label="Locked accounts" :value="$lockedAccounts->count()" :tone="$lockedAccounts->isNotEmpty() ? 'warn' : null" />
        <x-stat label="Owners and doctors without 2FA" :value="$twoFactorMissing" :tone="$twoFactorMissing ? 'warn' : null" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card card-body space-y-3">
            <h3 class="font-semibold text-slate-900">Find an account</h3>
            <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Name, email or mobile" aria-label="Find an account"></form>
            @if ($found !== null)
                @forelse ($found as $account)
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <p class="font-medium">{{ $account->name }} · {{ \App\Support\Mask::identifier($account->email ?? $account->mobile) }}</p>
                        @foreach ($foundSessions->get($account->id, []) as $session)
                            <p class="truncate text-xs text-slate-500">{{ $session->ip_address }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }} · {{ $session->user_agent }}</p>
                        @endforeach
                        <div class="mt-2 flex flex-wrap items-end gap-2">
                            <form method="POST" action="{{ route('admin.security.force-logout', $account) }}" data-confirm="Sign this person out everywhere?">@csrf<button class="btn btn-sm btn-secondary">Force sign-out</button></form>
                            <form method="POST" action="{{ route('admin.security.reset-2fa', $account) }}" class="flex gap-1" data-confirm="Reset this person's 2FA?">
                                @csrf
                                <input class="input min-h-9 w-40 py-1" name="reason" placeholder="Reason" required aria-label="Reason for resetting 2FA">
                                <button class="btn btn-sm btn-secondary">Reset 2FA</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nobody found.</p>
                @endforelse
            @endif
        </section>
        <section class="card card-body space-y-3">
            <h3 class="font-semibold text-slate-900">Blocked IP addresses</h3>
            <form method="POST" action="{{ route('admin.security.block-ip') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-field name="ip_address" label="IP address" required />
                <x-field name="reason" label="Reason" />
                <button class="btn btn-secondary">Block</button>
            </form>
            @foreach ($blockedIps as $blocked)
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span>{{ $blocked->ip_address }} {{ $blocked->reason }}</span>
                    <form method="POST" action="{{ route('admin.security.unblock-ip', $blocked) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Unblock</button></form>
                </div>
            @endforeach
        </section>
    </div>

    @if ($lockedAccounts->isNotEmpty())
        <section class="card mt-4">
            <div class="card-header"><h3 class="card-title">Locked accounts</h3></div>
            @foreach ($lockedAccounts as $account)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                    <span>{{ $account->name }} · {{ \App\Support\Mask::identifier($account->email ?? $account->mobile) }} · until {{ $account->locked_until->format('H:i') }}</span>
                    <form method="POST" action="{{ route('admin.security.force-logout', $account) }}" data-confirm="Sign this person out everywhere?">@csrf<button class="btn btn-sm btn-secondary">Force sign-out</button></form>
                </div>
            @endforeach
        </section>
    @endif

    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Security events</h3></div>
        @if ($events->isEmpty())
            <x-empty title="No security events" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Event</th><th>Account</th><th>IP</th></tr></thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td class="whitespace-nowrap">{{ $event->created_at->format('d M Y H:i') }}</td>
                            <td class="font-mono text-xs">{{ $event->type }}</td>
                            <td>{{ $event->identifier_masked }}</td>
                            <td>{{ $event->ip_address }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.admin>
