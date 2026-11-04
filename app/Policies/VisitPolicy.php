<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

/**
 * Clinical notes are for clinicians. A receptionist can register a patient
 * without being able to read why they came.
 */
class VisitPolicy extends TenantPolicy
{
    public function view(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'consultations.manage', $visit)
            || $this->allows($user, 'vitals.manage', $visit);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'consultations.manage') || $this->allows($user, 'vitals.manage');
    }

    public function update(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'consultations.manage', $visit) && $this->isOwnOrOwner($user, $visit);
    }

    public function recordVitals(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'vitals.manage', $visit);
    }

    public function viewNotes(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'consultations.manage', $visit);
    }

    public function prescribe(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'prescriptions.manage', $visit) && $this->isOwnOrOwner($user, $visit);
    }

    /**
     * A doctor signs only their own consultations. Owners may step in for their clinic.
     */
    private function isOwnOrOwner(User $user, Visit $visit): bool
    {
        return (int) $visit->doctor_user_id === $user->id
            || ($this->tenant->membership()?->hasRole('owner') ?? false);
    }
}
