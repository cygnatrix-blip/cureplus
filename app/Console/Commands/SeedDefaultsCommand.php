<?php

namespace App\Console\Commands;

use Database\Seeders\DefaultTemplateSeeder;
use Database\Seeders\MedicineCatalogueSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;

class SeedDefaultsCommand extends Command
{
    protected $signature = 'cureplus:seed-defaults';

    protected $description = 'Add starter plans, templates and medicines. Safe in production and never overwrites changes.';

    public function handle(): int
    {
        foreach ([PlanSeeder::class, DefaultTemplateSeeder::class, MedicineCatalogueSeeder::class] as $seeder) {
            $this->callSilently('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $this->info('Defaults are in place.');

        return self::SUCCESS;
    }
}
