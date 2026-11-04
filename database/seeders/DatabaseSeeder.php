<?php

namespace Database\Seeders;

use App\Services\Rbac\SyncRbac;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(SyncRbac $sync): void
    {
        $sync();
        $this->call([MedicineCatalogueSeeder::class, DefaultTemplateSeeder::class, PlanSeeder::class]);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
