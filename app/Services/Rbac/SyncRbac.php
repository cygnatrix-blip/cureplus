<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;

class SyncRbac
{
    /**
     * Idempotent: copies config/rbac.php into the database. Owner overrides are untouched.
     */
    public function __invoke(): void
    {
        foreach (config('rbac.permissions') as $key => $definition) {
            Permission::query()->updateOrCreate(['key' => $key], [
                'group_name' => $definition['group'],
                'label' => $definition['label'],
            ]);
        }

        $permissionIds = Permission::query()->pluck('id', 'key');

        foreach (config('rbac.roles') as $key => $definition) {
            $role = Role::query()->updateOrCreate(['key' => $key], [
                'name' => $definition['name'],
                'is_editable' => $definition['editable'],
            ]);

            $role->permissions()->sync(
                $definition['permissions'] === ['*']
                    ? $permissionIds->values()->all()
                    : $permissionIds->only($definition['permissions'])->values()->all()
            );
        }
    }
}
