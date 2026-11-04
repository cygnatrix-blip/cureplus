<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Messaging\AppointmentMessages;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private readonly DoctorCalendar $calendar,
        private readonly SequenceService $sequence,
        private readonly AppointmentMessages $messages,
    ) {}

    /**
     * Double-booking is stopped twice. The doctor's row lock makes concurrent bookings
     * for one doctor run one at a time, across every organization, so the overlap check
     * sees the truth. The unique index on (doctor, slot_key) is the backstop.
     */
    public function bookSlot(Patient $patient, int $doctorId, int $locationId, CarbonImmutable $startsAt, VisitType $visitType, string $source = 'staff'): Appointment
    {
        try {
            $appointment = DB::transaction(function () use ($patient, $doctorId, $locationId, $startsAt, $visitType, $source): Appointment {
                User::query()->whereKey($doctorId)->lockForUpdate()->firstOrFail();

                $organizationId = (int) $patient->organization_id;
                $endsAt = $this->calendar->isBookable($doctorId, $organizationId, $locationId, $startsAt);

                if ($endsAt === null || $this->calendar->hasConflict($doctorId, $startsAt, $endsAt)) {
                    throw new SlotUnavailableException;
                }

                return Appointment::withoutTenancy()->create([
                    'organization_id' => $organizationId,
                    'location_id' => $locationId,
                    'patient_id' => $patient->id,
                    'doctor_user_id' => $doctorId,
                    'kind' => 'booked',
                    'source' => $source,
                    'visit_type' => $visitType,
                    'scheduled_on' => $startsAt->toDateString(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'slot_key' => Appointment::slotKey($startsAt),
                    'token_number' => $this->nextToken($organizationId, $locationId, $doctorId, $startsAt),
                    'status' => AppointmentStatus::Booked,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new SlotUnavailableException;
        }

        $this->messages->send($appointment, 'appointment_booked');

        return $appointment;
    }

    /**
     * A walk-in joins the day's queue and holds no slot, so it never blocks the shared calendar.
     */
    public function walkIn(Patient $patient, int $doctorId, int $locationId, VisitType $visitType): Appointment
    {
        $today = CarbonImmutable::today();
        $organizationId = (int) $patient->organization_id;

        return Appointment::withoutTenancy()->create([
            'organization_id' => $organizationId,
            'location_id' => $locationId,
            'patient_id' => $patient->id,
            'doctor_user_id' => $doctorId,
            'kind' => 'walk_in',
            'source' => 'staff',
            'visit_type' => $visitType,
            'scheduled_on' => $today->toDateString(),
            'token_number' => $this->nextToken($organizationId, $locationId, $doctorId, $today),
            'status' => AppointmentStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    public function reschedule(Appointment $appointment, CarbonImmutable $startsAt): Appointment
    {
        try {
            return DB::transaction(function () use ($appointment, $startsAt): Appointment {
                $doctorId = (int) $appointment->doctor_user_id;
                $organizationId = (int) $appointment->organization_id;
                $locationId = (int) $appointment->location_id;

                User::query()->whereKey($doctorId)->lockForUpdate()->firstOrFail();

                $endsAt = $this->calendar->isBookable($doctorId, $organizationId, $locationId, $startsAt, $appointment->id);

                if ($endsAt === null || $this->calendar->hasConflict($doctorId, $startsAt, $endsAt, $appointment->id)) {
                    throw new SlotUnavailableException;
                }

                $sameDay = $appointment->scheduled_on->isSameDay($startsAt);

                $appointment->update([
                    'kind' => 'booked',
                    'scheduled_on' => $startsAt->toDateString(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'slot_key' => Appointment::slotKey($startsAt),
                    'token_number' => $sameDay ? $appointment->token_number : $this->nextToken($organizationId, $locationId, $doctorId, $startsAt),
                    'status' => AppointmentStatus::Booked,
                ]);

                return $appointment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new SlotUnavailableException;
        }
    }

    public function cancel(Appointment $appointment, string $reason): void
    {
        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancel_reason' => $reason,
            'cancelled_at' => now(),
            'slot_key' => null,
        ]);
    }

    public function transition(Appointment $appointment, AppointmentStatus $status): void
    {
        $appointment->update(array_filter([
            'status' => $status,
            'checked_in_at' => $status === AppointmentStatus::CheckedIn ? now() : null,
            'called_at' => $status === AppointmentStatus::InConsultation ? now() : null,
            'completed_at' => $status === AppointmentStatus::Completed ? now() : null,
        ]));

        if ($status === AppointmentStatus::InConsultation) {
            $this->messages->send($appointment, 'token_called');
        }

        // A no-show gives the time back so it can be booked again.
        if ($status === AppointmentStatus::NoShow) {
            $appointment->update(['slot_key' => null]);
        }
    }

    private function nextToken(int $organizationId, int $locationId, int $doctorId, CarbonImmutable $date): int
    {
        return $this->sequence->next($organizationId, "token:{$locationId}:{$doctorId}:{$date->format('Ymd')}");
    }
}
