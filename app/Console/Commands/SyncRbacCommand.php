<?php

namespace App\Console\Commands;

use App\Services\Rbac\SyncRbac;
use Illuminate\Console\Command;

class SyncRbacCommand extends Command
{
    protected $signature = 'cureplus:sync-rbac';

    protected $description = 'Copy roles and permissions from config/rbac.php into the database';

    public function handle(SyncRbac $sync): int
    {
        $sync();
        $this->info('Roles and permissions are in sync.');

        return self::SUCCESS;
    }
}
