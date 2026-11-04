<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '+919'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= 'Password123',
            'terms_accepted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->email_verified_at = now();
        });
    }

    public function unverified(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->email_verified_at = null;
        });
    }
}
