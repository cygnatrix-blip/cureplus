<?php

namespace App\Services\Rbac;

use App\Models\Membership;
use App\Models\OrganizationRolePermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;

class PermissionResolver
{
    public function allows(Membership $membership, string $permission): bool
    {
        if (! $membership->isActive()) {
            return false;
        }

        $matrix = $this->matrix((int) $membership->organization_id);

        foreach ($membership->roles as $role) {
            if (in_array($permission, $matrix[$role->key] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Effective permissions per role for one organization: platform defaults
     * with the owner's toggles applied.
     *
     * @return array<string, list<string>>
     */
    public function matrix(int $organizationId): array
    {
        return Cache::remember($this->cacheKey($organizationId), 3600, function () use ($organizationId): array {
            $allPermissions = Permission::query()->pluck('key', 'id');

            $overrides = OrganizationRolePermission::withoutTenancy()
                ->where('organization_id', $organizationId)
                ->get()
                ->groupBy('role_id');

            $matrix = [];

            foreach (Role::query()->with('permissions:id')->get() as $role) {
                if (! $role->is_editable) {
                    $matrix[$role->key] = $allPermissions->values()->all();

                    continue;
                }

                $granted = $role->permissions->pluck('id')->flip()->all();

                foreach ($overrides->get($role->id, []) as $override) {
                    if ($override->granted) {
                        $granted[$override->permission_id] = true;
                    } else {
                        unset($granted[$override->permission_id]);
                    }
                }

                $matrix[$role->key] = $allPermissions->only(array_keys($granted))->values()->all();
            }

            return $matrix;
        });
    }

    public function forget(int $organizationId): void
    {
        Cache::forget($this->cacheKey($organizationId));
    }

    private function cacheKey(int $organizationId): string
    {
        return "rbac.matrix.{$organizationId}";
    }
}
