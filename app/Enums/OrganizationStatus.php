<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case PendingVerification = 'pending_verification';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending verification',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }
}
