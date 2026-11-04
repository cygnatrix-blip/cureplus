<?php

namespace App\Services\Messaging;

use App\Models\Appointment;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;

/**
 * Turns an appointment into a message. Reads with no tenant scope, because the
 * scheduler and the patient portal both call this without a tenant set.
 */
class AppointmentMessages
{
    public function __construct(private readonly Notifier $notifier) {}

    public function send(Appointment $appointment, string $event, ?string $dedupeSuffix = null): void
    {
        $patient = Patient::withoutTenancy()->find($appointment->patient_id);
        $organization = Organization::query()->find($appointment->organization_id);
        $doctor = User::query()->find($appointment->doctor_user_id);

        if (! $patient || ! $organization || ! $doctor) {
            return;
        }

        $this->notifier->notifyPatient($patient, $event, [
            'doctor' => $doctor->name,
            'clinic' => $organization->name,
            'time' => $appointment->starts_at?->format('d M, h:i A') ?? 'today',
            'token' => $appointment->token_number,
        ], "appointment:{$appointment->id}:{$event}".($dedupeSuffix ? ":{$dedupeSuffix}" : ''));
    }
}
