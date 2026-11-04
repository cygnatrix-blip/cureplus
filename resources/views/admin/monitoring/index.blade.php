<x-layouts.admin title="Monitoring">
    @if ($emailWarning)
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">Email volume is approaching the daily limit of {{ $emailLimit }}. Consider moving to SES or ZeptoMail.</p>
    @endif
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Queue backlog" :value="$queueBacklog" :tone="$queueBacklog > 100 ? 'warn' : null" />
        <x-stat label="Failed jobs" :value="$failedJobCount" :tone="$failedJobCount ? 'bad' : null" />
        <x-stat label="Cron last ran" :value="$heartbeat?->diffForHumans() ?? 'Never'" :tone="! $heartbeat || $heartbeat->lt(now()->subMinutes(3)) ? 'bad' : null" />
        <x-stat label="Errors today" :value="$errorsToday" :tone="$errorsToday > 20 ? 'warn' : null" />
        <x-stat label="Emails today" :value="$emailsToday.' / '.$emailLimit" />
        <x-stat label="Message failures (24 h)" :value="$messageFailures" :tone="$messageFailures ? 'warn' : null" />
        <x-stat label="Last backup" :value="$lastBackup?->created_at?->diffForHumans() ?? 'Never'" :tone="! $lastBackup || $lastBackup->created_at->lt(now()->subDays(2)) ? 'bad' : null" />
        <x-stat label="Broken domains" :value="$brokenDomains" :tone="$brokenDomains ? 'bad' : null" />
        <x-stat label="Disk free" :value="round($diskFreeBytes / 1073741824, 1).' GB'" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Messages, last 24 hours</h2></div>
            @forelse ($messagesByChannel as $row)
                <div class="flex justify-between border-b border-slate-100 px-4 py-2 text-sm last:border-b-0"><span>{{ ucfirst($row->channel) }} · {{ $row->status }}</span><span class="font-medium">{{ $row->total }}</span></div>
            @empty
                <x-empty title="No messages" />
            @endforelse
        </section>
        <section class="card">
            <div class="card-header"><h2 class="card-title">Recent webhooks</h2></div>
            @forelse ($webhooks as $webhook)
                <div class="flex justify-between gap-2 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0"><span class="min-w-0 truncate">{{ $webhook->provider }} · {{ $webhook->event_type }}</span><span>{{ $webhook->status }}</span></div>
            @empty
                <x-empty title="No webhooks yet" />
            @endforelse
        </section>
        <section class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Failed jobs</h2></div>
            @forelse ($failedJobs as $job)
                <div class="flex justify-between border-b border-slate-100 px-4 py-2 text-sm last:border-b-0"><span>Job {{ $job->id }} · {{ $job->queue }}</span><span>{{ $job->failed_at }}</span></div>
            @empty
                <x-empty title="No failed jobs" />
            @endforelse
        </section>
    </div>
</x-layouts.admin>
