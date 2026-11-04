<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitAttachment;

class VisitAttachmentPolicy extends TenantPolicy
{
    public function view(User $user, VisitAttachment $attachment): bool
    {
        return $this->allows($user, 'consultations.manage', $attachment);
    }
}
