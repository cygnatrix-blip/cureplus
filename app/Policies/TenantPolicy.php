<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The tenant check is a second wall behind the global scope: a record that
 * arrives through an unscoped query still cannot pass here.
 */
abstract class TenantPolicy
{
    public function __construct(protected readonly TenantContext $tenant) {}

    protected function allows(User $user, string $permission, ?Model $record = null): bool
    {
        if ($record !== null && (int) $record->getAttribute('organization_id') !== $this->tenant->id()) {
            return false;
        }

        return Gate::forUser($user)->allows($permission);
    }
}
