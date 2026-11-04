<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'RCP/'.Str::upper(Str::random(8)),
            'kind' => 'receipt',
            'status' => 'unpaid',
            'subtotal_paise' => 50000,
            'exempt_paise' => 50000,
            'total_paise' => 50000,
            'issued_at' => now(),
        ];
    }
}
