<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Starter plans, safe for production. Prices are placeholders to edit in Super Admin.
 * firstOrCreate, so a redeploy never overwrites what an admin has changed.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $core = ['billing', 'reports', 'follow_ups', 'online_booking'];

        Plan::query()->firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter',
            'price_paise' => 49900,
            'trial_days' => 14,
            'limits' => ['doctors' => 1, 'staff' => 3, 'locations' => 2, 'messages_per_month' => 100],
            'features' => $core,
        ]);

        Plan::query()->firstOrCreate(['code' => 'clinic'], [
            'name' => 'Clinic',
            'price_paise' => 149900,
            'trial_days' => 14,
            'limits' => ['doctors' => 5, 'staff' => 15, 'locations' => 3, 'messages_per_month' => 1000],
            'features' => [...$core, 'online_payments', 'whatsapp'],
        ]);

        Plan::query()->firstOrCreate(['code' => 'enterprise'], [
            'name' => 'Enterprise',
            'price_paise' => 399900,
            'trial_days' => 14,
            'limits' => ['doctors' => null, 'staff' => null, 'locations' => null, 'messages_per_month' => null],
            'features' => ['*'],
        ]);
    }
}
