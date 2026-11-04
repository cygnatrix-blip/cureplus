<?php

namespace App\Enums;

enum OrganizationType: string
{
    case Clinic = 'clinic';
    case SoloPractice = 'solo_practice';

    public function label(): string
    {
        return match ($this) {
            self::Clinic => 'Clinic',
            self::SoloPractice => 'Solo practice',
        };
    }

    public function locationNoun(): string
    {
        return $this === self::Clinic ? 'Branch' : 'Chamber';
    }
}
