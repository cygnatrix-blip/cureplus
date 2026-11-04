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
}
