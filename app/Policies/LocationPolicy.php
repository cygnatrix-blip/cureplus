<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class LocationPolicy
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('locations.manage');
    }

    public function create(User $user): bool
    {
        return Gate::forUser($user)->allows('locations.manage');
    }

    public function update(User $user, Location $location): bool
    {
        return $this->inTenant($location) && Gate::forUser($user)->allows('locations.manage');
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }

    // Second wall behind the global scope: an unscoped query still cannot pass here.
    private function inTenant(Location $location): bool
    {
        return (int) $location->organization_id === $this->tenant->id();
    }
}
