<?php

use App\Jobs\MoveDriveFiles;
use App\Jobs\RunBackup;
use App\Models\BackupRun;
use App\Models\MessageLog;
use App\Models\Organization;
use App\Models\OrganizationCounter;
use App\Models\OtpCode;
use App\Models\RestoreTest;
use App\Models\SecurityEvent;
use App\Models\SystemHeartbeat;
use App\Services\AppointmentChat;
use App\Services\Backups\BackupService;
use App\Services\Billing\SubscriptionService;
use App\Services\Domains\DomainService;
use App\Services\Messaging\ReminderScheduler;
use App\Services\OrganizationVerification;
use App\Services\Storage\DriveFiles;
use App\Services\VideoPayments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/*
| Everything here uses Schedule::call, never Schedule::command or ->exec(). Those start
| a child process through proc_open, which Hostinger disables in disable_functions.
| SchedulerTest fails if a process-based task is added.
|
| Tasks run in order inside one PHP process, so the queue worker stays last: it can
| occupy the rest of the minute, and earlier tasks must not wait behind it.
*/

Schedule::call(fn () => SystemHeartbeat::beat('scheduler'))
    ->name('heartbeat')
    ->everyMinute();

Schedule::call(fn () => app(VideoPayments::class)->releaseUnpaid())
    ->name('release-unpaid-video-calls')
    ->everyFiveMinutes();

Schedule::call(fn () => app(AppointmentChat::class)->closeStale())
    ->name('close-stale-chats')
    ->dailyAt('02:15');

Schedule::call(fn () => app(DriveFiles::class)->syncPending())
    ->name('copy-files-to-google-drive')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// Practices that agreed to rely on Google Drive also have newer files' server copies removed.
Schedule::call(function (): void {
    Organization::query()->where('drive_server_copy', DriveFiles::REMOVE)->pluck('id')
        ->each(fn (int $id) => MoveDriveFiles::dispatch($id, MoveDriveFiles::REMOVE));
})->name('remove-agreed-server-copies')->dailyAt('03:30');

Schedule::call(function (): void {
    OtpCode::query()->where('expires_at', '<', now()->subDay())->delete();
    SecurityEvent::query()->where('created_at', '<', now()->subDays(90))->delete();
    DB::table('cache')->where('expiration', '<', now()->getTimestamp())->delete();
    DB::table('cache_locks')->where('expiration', '<', now()->getTimestamp())->delete();
    // Yesterday's queue tokens are finished with, and one row per doctor per day adds up.
    OrganizationCounter::withoutTenancy()->where('counter_key', 'like', 'token:%')->where('updated_at', '<', now()->subDays(2))->delete();
    MessageLog::withoutTenancy()->where('created_at', '<', now()->subDays(180))->delete();
    Artisan::call('queue:prune-failed', ['--hours' => 24 * 30]);
})->name('housekeeping')->dailyAt('02:30');

// Queued, not run here: a backup can take far longer than the scheduler's minute.
Schedule::call(fn () => RunBackup::dispatch())
    ->name('backup')
    ->dailyAt('01:30');

// Reads the newest backup and records the result, so "restore tested monthly" has evidence.
Schedule::call(function (): void {
    $run = BackupRun::query()->where('status', 'completed')->latest('id')->first();

    if ($run === null) {
        return;
    }

    try {
        $result = app(BackupService::class)->verify($run);
        RestoreTest::query()->create(['backup_run_id' => $run->id, 'result' => 'passed', 'notes' => "Automatic check: {$result['tables']} tables, {$result['rows']} rows readable."]);
    } catch (Throwable $exception) {
        RestoreTest::query()->create(['backup_run_id' => $run->id, 'result' => 'failed', 'notes' => 'Automatic check failed: '.class_basename($exception)]);
    }
})->name('restore-check')->monthlyOn(1, '04:00');

Schedule::call(fn () => app(DomainService::class)->recheckActive())
    ->name('domains')
    ->dailyAt('04:30');

Schedule::call(fn () => app(SubscriptionService::class)->enforce())
    ->name('subscriptions')
    ->dailyAt('03:00');

Schedule::call(fn () => app(OrganizationVerification::class)->sendAccessReminders())
    ->name('access-reminders')
    ->dailyAt('09:00');

Schedule::call(fn () => app(ReminderScheduler::class)->run())
    ->name('reminders')
    ->everyFifteenMinutes();

Schedule::call(fn () => Artisan::call('queue:work', [
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--tries' => 3,
    '--memory' => 256,
]))->name('queue-worker')->everyMinute()->withoutOverlapping(5);
