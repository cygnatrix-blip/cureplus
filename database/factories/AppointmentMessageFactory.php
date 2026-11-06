<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentMessage>
 */
class AppointmentMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'organization_id' => fn (array $attributes) => Appointment::withoutTenancy()->find($attributes['appointment_id'])?->organization_id,
            'sender_type' => AppointmentMessage::FROM_PATIENT,
            'body' => fake()->sentence(),
        ];
    }

    public function fromDoctor(): static
    {
        return $this->state(fn () => ['sender_type' => AppointmentMessage::FROM_DOCTOR]);
    }
}
