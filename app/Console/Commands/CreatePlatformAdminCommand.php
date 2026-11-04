<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'cureplus:create-admin {email} {name} {--role=super_admin}';

    protected $description = 'Create a platform admin. The generated password is shown once.';

    public function handle(): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $role = (string) $this->option('role');

        if (! array_key_exists($role, config('rbac.platform_roles'))) {
            $this->error('Unknown role: '.$role);

            return self::FAILURE;
        }

        if (PlatformAdmin::withTrashed()->where('email', $email)->exists()) {
            $this->error('An admin with this email already exists.');

            return self::FAILURE;
        }

        $password = Str::password(20);

        PlatformAdmin::create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ]);

        $this->info('Admin created. Save this password now; it will not be shown again:');
        $this->line($password);

        return self::SUCCESS;
    }
}
