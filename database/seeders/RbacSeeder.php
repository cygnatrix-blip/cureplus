<?php

namespace Database\Seeders;

use App\Services\Rbac\SyncRbac;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(SyncRbac $sync): void
    {
        $sync();
    }
}
