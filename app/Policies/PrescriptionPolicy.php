<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy extends TenantPolicy
{
    public function view(User $user, Prescription $prescription): bool
    {
        return $this->allows($user, 'prescriptions.manage', $prescription)
            || $this->allows($user, 'patients.view', $prescription);
    }
}
