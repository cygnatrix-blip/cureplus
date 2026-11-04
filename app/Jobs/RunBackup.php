<?php

namespace App\Jobs;

use App\Services\Backups\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $trigger = 'schedule') {}

    public function handle(BackupService $backups): void
    {
        $backups->run($this->trigger);
    }
}
