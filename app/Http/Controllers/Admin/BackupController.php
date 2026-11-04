<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackup;
use App\Models\BackupRun;
use App\Models\RestoreTest;
use App\Services\AuditLogger;
use App\Services\Backups\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups.index', [
            'runs' => BackupRun::query()->latest('id')->paginate(30),
            'tests' => RestoreTest::query()->with('backupRun:id,filename')->latest('id')->limit(12)->get(),
        ]);
    }

    public function store(AuditLogger $audit): RedirectResponse
    {
        RunBackup::dispatch('manual');
        $audit->record('backups.triggered');

        return back()->with('success', 'Backup queued. It will run within a minute.');
    }

    public function verify(Request $request, BackupRun $run, BackupService $backups): RedirectResponse
    {
        try {
            $result = $backups->verify($run);
            RestoreTest::query()->create(['backup_run_id' => $run->id, 'result' => 'passed', 'notes' => "{$result['tables']} tables, {$result['rows']} rows readable.", 'tested_by' => $request->user('platform')->id]);

            return back()->with('success', "Backup is readable: {$result['tables']} tables, {$result['rows']} rows.");
        } catch (Throwable $exception) {
            RestoreTest::query()->create(['backup_run_id' => $run->id, 'result' => 'failed', 'notes' => class_basename($exception), 'tested_by' => $request->user('platform')->id]);

            return back()->with('error', 'This backup could not be read.');
        }
    }

    public function logRestoreTest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_run_id' => ['nullable', 'integer', 'exists:backup_runs,id'],
            'result' => ['required', 'in:passed,failed'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        RestoreTest::query()->create($data + ['tested_by' => $request->user('platform')->id]);

        return back()->with('success', 'Restore test logged.');
    }

    public function download(BackupRun $run, BackupService $backups, AuditLogger $audit): StreamedResponse
    {
        abort_unless($run->status === 'completed' && $backups->disk()->exists($backups->path($run)), 404);

        $audit->record('backups.downloaded', $run);

        return $backups->disk()->download($backups->path($run), (string) $run->filename);
    }
}
