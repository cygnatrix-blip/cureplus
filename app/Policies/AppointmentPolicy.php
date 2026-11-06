<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'appointments.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'appointments.manage');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->allows($user, 'appointments.manage', $appointment);
    }

    /**
     * The chat carries clinical detail, so on the practice's side only the appointment's own
     * doctor reads it. Not the owner and not the front desk.
     */
    public function chat(User $user, Appointment $appointment): bool
    {
        return $this->allows($user, 'consultations.manage', $appointment) && (int) $appointment->doctor_user_id === $user->id;
    }
}
