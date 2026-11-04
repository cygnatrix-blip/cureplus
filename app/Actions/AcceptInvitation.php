<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AcceptInvitation
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function __invoke(Invitation $invitation, User $user): Membership
    {
        return DB::transaction(function () use ($invitation, $user): Membership {
            // Lock first: two simultaneous clicks must not both accept one invitation.
            $invitation = Invitation::withoutTenancy()->lockForUpdate()->findOrFail($invitation->id);

            if (! $invitation->isPending()) {
                throw new NotFoundHttpException;
            }

            $organizationId = $invitation->organization_id;

            $membership = Membership::withoutTenancy()->withTrashed()
                ->where('organization_id', $organizationId)
                ->where('user_id', $user->id)
                ->first()
                ?? new Membership(['organization_id' => $organizationId, 'user_id' => $user->id]);

            $membership->fill(['status' => 'active', 'joined_at' => now()]);
            $membership->deleted_at = null;

            $roleIds = Role::query()->whereIn('key', $invitation->role_keys)->pluck('id', 'key');

            if ($roleIds->has('doctor') && $membership->doctor_slug === null) {
                $membership->doctor_slug = Membership::uniqueDoctorSlug((int) $organizationId, $user->name);
            }

            $membership->save();
            $membership->roles()->sync($roleIds->values());
            $membership->locations()->sync(
                // Re-filtered by organization: a stored id must never grant another tenant's location.
                Location::withoutTenancy()
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $invitation->location_ids)
                    ->pluck('id')
            );

            $invitation->forceFill(['accepted_at' => now()])->saveQuietly();
            $this->permissions->forget((int) $organizationId);

            return $membership;
        });
    }
}
