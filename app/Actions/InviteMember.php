<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteMember
{
    /**
     * @param  array{name?: string|null, email: string, role_keys: list<string>, location_ids: list<int>}  $data
     */
    public function __invoke(Organization $organization, User $inviter, array $data): Invitation
    {
        $token = Str::random(64);

        $invitation = Invitation::create([
            'organization_id' => $organization->id,
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'token_hash' => Invitation::hashToken($token),
            'role_keys' => $data['role_keys'],
            'location_ids' => $data['location_ids'],
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(config('cureplus.invitations.ttl_days')),
        ]);

        Notification::route('mail', $data['email'])->notify(new InvitationNotification($organization->name, route('invitations.show', $token)));

        return $invitation;
    }
}
