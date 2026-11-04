<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'generic_name' => fake()->unique()->word().'cillin',
            'brand_name' => fake()->unique()->word(),
            'strength' => '500 mg',
            'form' => 'Tablet',
            'manufacturer' => fake()->company(),
        ];
    }
}
