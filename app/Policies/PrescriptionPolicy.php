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

    /**
     * A printed prescription is a legal document, so it needs a verified practice.
     */
    public function print(User $user, Prescription $prescription): bool
    {
        return $this->organizationIsApproved() && $this->view($user, $prescription);
    }
}
