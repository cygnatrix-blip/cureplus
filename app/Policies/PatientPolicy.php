<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->allows($user, 'patients.view', $patient);
    }

    /**
     * Reading another organization's records is for clinicians, with consent on top.
     */
    public function viewHistory(User $user, Patient $patient): bool
    {
        return $this->allows($user, 'consultations.manage', $patient);
    }

    public function create(User $user): bool
    {
        return $this->organizationIsApproved() && $this->allows($user, 'patients.manage');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->allows($user, 'patients.manage', $patient);
    }
}
