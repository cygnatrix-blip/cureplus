<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Visit;

/**
 * Clinical notes are for clinicians. A receptionist can register a patient without being
 * able to read why they came, and only the treating doctor can change what was written.
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
        return $this->allows($user, 'consultations.manage', $visit) && (int) $visit->doctor_user_id === $user->id;
    }

    public function recordVitals(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'vitals.manage', $visit) && ! $this->isOwnerOnlyViewing($user, (int) $visit->doctor_user_id);
    }

    public function viewNotes(User $user, Visit $visit): bool
    {
        return $this->allows($user, 'consultations.manage', $visit);
    }

    public function prescribe(User $user, Visit $visit): bool
    {
        // A prescription is the treating doctor's own legal act. Being the owner is not enough.
        return $this->allows($user, 'prescriptions.manage', $visit) && (int) $visit->doctor_user_id === $user->id;
    }

    /**
     * Opening a consultation writes to the record and moves the queue, so an owner who is
     * not this patient's doctor does not do it. Nurses still open it to record vitals.
     */
    public function start(User $user, Appointment $appointment): bool
    {
        return $this->create($user) && ! $this->isOwnerOnlyViewing($user, (int) $appointment->doctor_user_id);
    }

    /**
     * Medical records belong to the treating doctor. Owners can read them for their practice
     * but not change them, unless they are that patient's doctor.
     */
    private function isOwnerOnlyViewing(User $user, int $doctorId): bool
    {
        return ($this->tenant->membership()?->hasRole('owner') ?? false) && $doctorId !== $user->id;
    }
}
