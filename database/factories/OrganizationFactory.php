<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'type' => OrganizationType::Clinic,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Organization $organization): void {
            $organization->status ??= OrganizationStatus::Active;
            $organization->approved_at ??= now();
        });
    }

    public function pending(): static
    {
        return $this->afterMaking(function (Organization $organization): void {
            $organization->status = OrganizationStatus::Pending;
            $organization->approved_at = null;
        });
    }
}
