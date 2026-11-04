<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;

class TenantDoctors
{
    /**
     * Active doctors of the current tenant, optionally only those at one location.
     *
     * @return Collection<int, User>
     */
    public function all(?int $locationId = null): Collection
    {
        return Membership::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
            ->when($locationId, fn ($query) => $query->whereHas('locations', fn ($query) => $query->where('locations.id', $locationId)))
            ->with(['user:id,name,email,mobile'])
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy('name')
            ->values();
    }

    public function isDoctor(int $userId): bool
    {
        return Membership::query()
            ->active()
            ->where('user_id', $userId)
            ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
            ->exists();
    }
}
