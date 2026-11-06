<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Services\PlatformAdminRecovery;
use Illuminate\Console\Command;

/**
 * For when no other admin can sign in. It only runs on the server, so using it needs the
 * same access as reading the database: it is not a way in for anyone on the web.
 */
class ResetPlatformAdminCommand extends Command
{
    protected $signature = 'cureplus:reset-admin {email} {--force : Skip the confirmation}';

    protected $description = 'Give a locked-out platform admin a temporary password. It is shown once.';

    public function handle(PlatformAdminRecovery $recovery): int
    {
        $admin = PlatformAdmin::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if ($admin === null) {
            $this->error('No admin has that email.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Reset {$admin->name}? Their password and two-factor sign-in will be replaced, and they will be signed out everywhere.")) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        $password = $recovery->reset($admin, ['via' => 'command line']);

        if ($admin->status !== 'active') {
            $this->warn('This admin is deactivated, so they still cannot sign in. Another admin can reactivate them.');
        }

        $this->info('Password reset. Save this temporary password now; it will not be shown again:');
        $this->line($password);
        $this->line('At the next sign-in they will set up two-factor sign-in again.');

        return self::SUCCESS;
    }
}
