<?php

namespace Database\Factories;

use App\Models\PatientAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAccount>
 */
class PatientAccountFactory extends Factory
{
    public function definition(): array
    {
        return ['email' => fake()->unique()->safeEmail(), 'mobile' => '+919'.fake()->unique()->numerify('#########'), 'name' => fake()->name()];
    }
}
