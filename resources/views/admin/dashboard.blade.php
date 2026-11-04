<x-layouts.admin title="Dashboard">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Active organizations" :value="$activeOrganizations" />
        <x-stat label="Awaiting approval" :value="$pendingOrganizations" :tone="$pendingOrganizations ? 'warn' : null" />
        <x-stat label="Doctors" :value="$doctors" />
        <x-stat label="Signups this month" :value="$signupsThisMonth" />
        <x-stat label="Patients" :value="$patients" />
        @if (config('plans.enabled'))<x-stat label="Monthly recurring revenue" :value="\App\Support\Money::format($mrr)" />@endif
        <x-stat label="Failed payments" :value="$failedPayments" :tone="$failedPayments ? 'bad' : null" />
        <x-stat label="Open tickets" :value="$openTickets" :tone="$openTickets ? 'warn' : null" />
    </div>
    <h2 class="mt-6 mb-3 font-semibold text-slate-900">System health</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Failed logins (24 h)" :value="$failedLogins24h" :tone="$failedLogins24h > 20 ? 'warn' : null" />
        <x-stat label="Queue backlog" :value="$queueBacklog" :tone="$queueBacklog > 100 ? 'warn' : null" />
        <x-stat label="Failed jobs" :value="$failedJobs" :tone="$failedJobs ? 'bad' : null" />
        <x-stat label="Cron last ran" :value="$heartbeat?->diffForHumans() ?? 'Never'" :tone="! $heartbeat || $heartbeat->lt(now()->subMinutes(3)) ? 'bad' : null" />
    </div>
</x-layouts.admin>
