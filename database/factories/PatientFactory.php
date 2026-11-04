<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_code' => 'P-'.Str::upper(Str::random(8)),
            'name' => fake()->name(),
            'mobile' => '+919'.fake()->unique()->numerify('#########'),
            'gender' => fake()->randomElement(['male', 'female']),
        ];
    }
}
