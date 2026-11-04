<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Models\CustomDomain;
use App\Models\MessageLog;
use App\Models\SystemHeartbeat;
use App\Models\WebhookEvent;
use App\Services\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function __invoke(): View
    {
        $emailsToday = Metrics::today('emails_sent');
        $emailLimit = (int) config('cureplus.monitoring.daily_email_limit');
        $since = now()->subDay();

        return view('admin.monitoring.index', [
            'queueBacklog' => DB::table('jobs')->count(),
            'oldestJobAt' => DB::table('jobs')->min('created_at'),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(20)->get(['id', 'queue', 'failed_at']),
            'failedJobCount' => DB::table('failed_jobs')->count(),
            'heartbeat' => SystemHeartbeat::query()->find('scheduler')?->beat_at,
            'errorsToday' => Metrics::today('errors'),
            'emailsToday' => $emailsToday,
            'emailLimit' => $emailLimit,
            'emailWarning' => $emailsToday >= $emailLimit * 0.8,
            'messageFailures' => MessageLog::withoutTenancy()->where('status', 'failed')->where('created_at', '>=', $since)->count(),
            'messagesByChannel' => MessageLog::withoutTenancy()->where('created_at', '>=', $since)->selectRaw('channel, status, COUNT(*) total')->groupBy('channel', 'status')->toBase()->get(),
            'webhooks' => WebhookEvent::withoutTenancy()->latest('id')->limit(20)->get(),
            'lastBackup' => BackupRun::query()->where('status', 'completed')->latest('id')->first(),
            'brokenDomains' => CustomDomain::withoutTenancy()->where('status', 'dns_broken')->count(),
            'diskFreeBytes' => (int) @disk_free_space(storage_path()),
        ]);
    }
}
