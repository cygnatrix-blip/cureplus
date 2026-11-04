<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class MembershipPolicy
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('staff.manage');
    }

    public function update(User $user, Membership $membership): bool
    {
        return (int) $membership->organization_id === $this->tenant->id()
            && Gate::forUser($user)->allows('staff.manage');
    }
}
