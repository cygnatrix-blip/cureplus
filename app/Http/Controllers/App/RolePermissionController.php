<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\OrganizationRolePermission;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\Rbac\PermissionResolver;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(TenantContext $tenant, PermissionResolver $resolver): View
    {
        return view('app.roles.index', [
            'roles' => Role::query()->where('is_editable', true)->orderBy('id')->get(),
            'permissions' => Permission::query()->when(! config('plans.enabled'), fn ($query) => $query->where('key', '!=', 'subscription.manage'))->orderBy('group_name')->orderBy('id')->get()->groupBy('group_name'),
            'matrix' => $resolver->matrix((int) $tenant->id()),
        ]);
    }

    public function update(Request $request, TenantContext $tenant, PermissionResolver $resolver, AuditLogger $audit): RedirectResponse
    {
        $submitted = (array) $request->input('grants', []);
        $roles = Role::query()->where('is_editable', true)->with('permissions:id')->get();
        $permissions = Permission::query()->get();

        DB::transaction(function () use ($submitted, $roles, $permissions): void {
            foreach ($roles as $role) {
                // The form sends a blank entry per role, so "everything unticked" is
                // distinct from a role that was not submitted at all.
                if (! array_key_exists($role->key, $submitted)) {
                    continue;
                }

                $defaults = $role->permissions->pluck('id')->all();
                $granted = (array) $submitted[$role->key];

                foreach ($permissions as $permission) {
                    $wanted = in_array($permission->key, $granted, true);
                    $where = ['role_id' => $role->id, 'permission_id' => $permission->id];

                    // Only differences from the default are stored, so later
                    // improvements to the defaults still reach this organization.
                    if ($wanted === in_array($permission->id, $defaults, true)) {
                        OrganizationRolePermission::query()->where($where)->delete();
                    } else {
                        OrganizationRolePermission::query()->updateOrCreate($where, ['granted' => $wanted]);
                    }
                }
            }
        });

        $resolver->forget((int) $tenant->id());
        $audit->record('roles.permissions_changed', $tenant->organization());

        return back()->with('success', 'Permissions saved.');
    }
}
