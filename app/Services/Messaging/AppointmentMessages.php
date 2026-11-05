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

        // The link is what they are paying for, so it is not sent until they have.
        if ($appointment->isAwaitingPayment()) {
            return;
        }

        $variables = [
            'doctor' => $doctor->name,
            'clinic' => $organization->name,
            'time' => $appointment->starts_at?->format('d M, h:i A') ?? 'today',
            'token' => $appointment->token_number,
        ];

        // A video patient needs the link, and must not be told to come in.
        if ($appointment->isOnline()) {
            $event = match ($event) {
                'token_called' => 'video_consult_now',
                'appointment_booked', 'appointment_reminder', 'appointment_moved' => 'video_consult',
                default => $event,
            };
        }

        if (in_array($event, ['video_consult', 'video_consult_now'], true)) {
            $variables['link'] = (string) $appointment->video_link;
        }

        $this->notifier->notifyPatient($patient, $event, $variables, "appointment:{$appointment->id}:{$event}".($dedupeSuffix ? ":{$dedupeSuffix}" : ''));
    }
}
