<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Backups\BackupService;
use Illuminate\Console\Command;

class RestoreBackupCommand extends Command
{
    protected $signature = 'cureplus:backup-restore {id : The backup run id} {--force : Do not ask for confirmation}';

    protected $description = 'Replace the current database with a backup. This destroys what is there now.';

    public function handle(BackupService $backups): int
    {
        $run = BackupRun::query()->find($this->argument('id'));

        if ($run === null || $run->status !== 'completed') {
            $this->error('No completed backup has that id.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace the whole database with the backup from {$run->created_at}?")) {
            return self::FAILURE;
        }

        $backups->restore($run);
        $this->info('Restored. Run: php artisan optimize:clear');

        return self::SUCCESS;
    }
}
