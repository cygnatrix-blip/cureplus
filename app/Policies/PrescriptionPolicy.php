<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'prescriptions.view') || $this->allows($user, 'prescriptions.manage');
    }

    /**
     * A prescription reveals a diagnosis and treatment, so being able to see a patient's
     * contact details is not enough. An owner can grant this to other roles.
     */
    public function view(User $user, Prescription $prescription): bool
    {
        return $this->allows($user, 'prescriptions.view', $prescription)
            || $this->allows($user, 'prescriptions.manage', $prescription);
    }

    /**
     * A printed prescription is a legal document, so it needs a verified practice.
     */
    public function print(User $user, Prescription $prescription): bool
    {
        return $this->organizationIsApproved() && $this->view($user, $prescription);
    }
}
