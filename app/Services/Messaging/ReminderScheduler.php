<?php

namespace App\Services\Messaging;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;

/**
 * Runs every 15 minutes from the scheduler. It only plans work, and each planned message
 * has a unique dedupe key, so a repeated or overlapping run cannot send anything twice.
 */
class ReminderScheduler
{
    public function __construct(private readonly AppointmentMessages $appointments, private readonly Notifier $notifier) {}

    /**
     * @return array{appointments: int, follow_ups: int, missed: int}
     */
    public function run(): array
    {
        $missed = $this->sortOutOverdueFollowUps();

        if (! $this->isDaytime()) {
            return ['appointments' => 0, 'follow_ups' => 0, 'missed' => $missed];
        }

        return ['appointments' => $this->remindAppointments(), 'follow_ups' => $this->remindFollowUps(), 'missed' => $missed];
    }

    private function isDaytime(): bool
    {
        $hour = now()->hour;

        return $hour >= config('messages.reminders.quiet_before') && $hour < config('messages.reminders.quiet_after');
    }

    private function remindAppointments(): int
    {
        $count = 0;

        // The day-before window starts at 22 hours, so an appointment made late still gets one.
        foreach (['day-before' => [2, 26], 'same-day' => [0, 2]] as $window => [$fromHours, $toHours]) {
            $appointments = Appointment::withoutTenancy()
                ->where('status', AppointmentStatus::Booked)
                ->whereBetween('starts_at', [now()->addHours($fromHours), now()->addHours($toHours)])
                ->orderBy('starts_at')
                ->limit((int) config('messages.reminders.batch_size'))
                ->get();

            foreach ($appointments as $appointment) {
                $this->appointments->send($appointment, 'appointment_reminder', $window);
                $count++;
            }
        }

        return $count;
    }

    private function remindFollowUps(): int
    {
        $followUps = FollowUp::withoutTenancy()
            ->where('status', 'pending')
            ->whereBetween('due_on', [today()->startOfDay(), today()->addDay()->endOfDay()])
            ->limit((int) config('messages.reminders.batch_size'))
            ->get();

        foreach ($followUps as $followUp) {
            $patient = Patient::withoutTenancy()->find($followUp->patient_id);
            $organization = Organization::query()->find($followUp->organization_id);
            $doctor = User::query()->find($followUp->doctor_user_id);

            if ($patient && $organization && $doctor) {
                $this->notifier->notifyPatient($patient, 'follow_up_due', [
                    'doctor' => $doctor->name,
                    'clinic' => $organization->name,
                    'date' => $followUp->due_on->format('d M'),
                ], "follow-up:{$followUp->id}");

                $followUp->forceFill(['last_reminded_at' => now()])->saveQuietly();
            }
        }

        return $followUps->count();
    }

    /**
     * A patient who came back is done. One who did not goes on the staff call list.
     */
    private function sortOutOverdueFollowUps(): int
    {
        $overdue = FollowUp::withoutTenancy()
            ->where('status', 'pending')
            ->where('due_on', '<', today()->startOfDay())
            ->limit((int) config('messages.reminders.batch_size'))
            ->get();

        $missed = 0;

        foreach ($overdue as $followUp) {
            $cameBack = Visit::withoutTenancy()
                ->where('patient_id', $followUp->patient_id)
                ->whereKeyNot((int) $followUp->visit_id)
                ->where('visited_on', '>=', $followUp->created_at)
                ->exists();

            $followUp->forceFill(['status' => $cameBack ? 'done' : 'missed'])->saveQuietly();
            $missed += $cameBack ? 0 : 1;
        }

        return $missed;
    }
}
