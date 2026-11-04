<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\DoctorAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The one calendar a doctor has, across all organizations. Each organization only
 * learns that a time is busy, never who it is busy with or where.
 */
class DoctorCalendar
{
    /**
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, available: bool}>
     */
    public function slots(int $doctorId, int $organizationId, int $locationId, CarbonImmutable $date): array
    {
        $windows = DoctorAvailability::withoutTenancy()
            ->where('doctor_user_id', $doctorId)
            ->where('organization_id', $organizationId)
            ->where('location_id', $locationId)
            ->where('weekday', $date->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($windows->isEmpty()) {
            return [];
        }

        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();
        $busy = $this->busyPeriods($doctorId, $organizationId, $locationId, $dayStart, $dayEnd);
        $now = CarbonImmutable::now();
        $slots = [];

        foreach ($windows as $window) {
            $start = $date->setTimeFromTimeString($window->start_time);
            $end = $date->setTimeFromTimeString($window->end_time);

            for ($slot = $start; $slot->addMinutes($window->slot_minutes)->lte($end); $slot = $slot->addMinutes($window->slot_minutes)) {
                $slotEnd = $slot->addMinutes($window->slot_minutes);
                $slots[] = [
                    'starts_at' => $slot,
                    'ends_at' => $slotEnd,
                    'available' => $slot->gt($now) && ! $this->overlapsAny($busy, $slot, $slotEnd),
                ];
            }
        }

        return $slots;
    }

    /**
     * Must be called inside the transaction that holds the doctor's row lock.
     */
    public function isBookable(int $doctorId, int $organizationId, int $locationId, CarbonImmutable $startsAt, ?int $ignoreAppointmentId = null): ?CarbonImmutable
    {
        foreach ($this->slots($doctorId, $organizationId, $locationId, $startsAt) as $slot) {
            if ($slot['starts_at']->equalTo($startsAt)) {
                if ($slot['available']) {
                    return $slot['ends_at'];
                }

                // The doctor's own current slot does not count against a reschedule within it.
                if ($ignoreAppointmentId !== null && ! $this->hasConflict($doctorId, $slot['starts_at'], $slot['ends_at'], $ignoreAppointmentId)
                    && $slot['starts_at']->gt(CarbonImmutable::now())) {
                    return $slot['ends_at'];
                }

                return null;
            }
        }

        return null;
    }

    public function hasConflict(int $doctorId, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreAppointmentId = null): bool
    {
        return Appointment::withoutTenancy()
            ->where('doctor_user_id', $doctorId)
            ->whereIn('status', AppointmentStatus::slotHoldingValues())
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreAppointmentId, fn (Builder $query) => $query->whereKeyNot($ignoreAppointmentId))
            ->exists();
    }

    /**
     * Whether a new weekly window would clash with the doctor's hours anywhere else.
     */
    public function availabilityOverlaps(int $doctorId, int $weekday, string $start, string $end): bool
    {
        return DoctorAvailability::withoutTenancy()
            ->where('doctor_user_id', $doctorId)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    /**
     * @return Collection<int, array{CarbonImmutable, CarbonImmutable}>
     */
    private function busyPeriods(int $doctorId, int $organizationId, int $locationId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $appointments = Appointment::withoutTenancy()
            ->where('doctor_user_id', $doctorId)
            ->whereIn('status', AppointmentStatus::slotHoldingValues())
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment) => [$appointment->starts_at->toImmutable(), $appointment->ends_at->toImmutable()]);

        $blocks = CalendarBlock::withoutTenancy()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('doctor_user_id', $doctorId)
                    ->where(fn (Builder $query) => $query->where('applies_everywhere', true)->orWhere('organization_id', $organizationId)))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('doctor_user_id')
                    ->where('organization_id', $organizationId)
                    ->where(fn (Builder $query) => $query->whereNull('location_id')->orWhere('location_id', $locationId))))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (CalendarBlock $block) => [$block->starts_at->toImmutable(), $block->ends_at->toImmutable()]);

        return $appointments->toBase()->merge($blocks->toBase());
    }

    /**
     * @param  Collection<int, array{CarbonImmutable, CarbonImmutable}>  $busy
     */
    private function overlapsAny(Collection $busy, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $busy->contains(fn (array $period) => $period[0]->lt($end) && $period[1]->gt($start));
    }
}
