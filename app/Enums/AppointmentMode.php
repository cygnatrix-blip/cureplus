<?php

namespace App\Enums;

enum AppointmentMode: string
{
    case InPerson = 'in_person';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'At the clinic',
            self::Online => 'Video call',
        };
    }
}
