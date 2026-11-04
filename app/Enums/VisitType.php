<?php

namespace App\Enums;

enum VisitType: string
{
    case New = 'new';
    case FollowUp = 'follow_up';
    case Emergency = 'emergency';
    case Procedure = 'procedure';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::FollowUp => 'Follow-up',
            self::Emergency => 'Emergency',
            self::Procedure => 'Procedure',
        };
    }
}
