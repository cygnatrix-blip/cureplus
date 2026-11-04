<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case InConsultation = 'in_consultation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::CheckedIn => 'Waiting',
            self::InConsultation => 'With doctor',
            self::Completed => 'Done',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    /**
     * Whether an appointment in this state still occupies the doctor's time.
     */
    public function holdsSlot(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }

    /**
     * @return list<string>
     */
    public static function slotHoldingValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->holdsSlot()),
        ));
    }
}
