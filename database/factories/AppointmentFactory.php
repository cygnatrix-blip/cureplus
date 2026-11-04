<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    private static int $token = 0;

    public function definition(): array
    {
        return [
            'kind' => 'walk_in',
            'source' => 'staff',
            'visit_type' => VisitType::New,
            'scheduled_on' => today()->toDateString(),
            'token_number' => ++self::$token,
            'status' => AppointmentStatus::CheckedIn,
        ];
    }
}
