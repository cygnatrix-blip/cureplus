<?php

namespace App\Services;

use App\Mail\ClinicMessageMail;
use App\Models\Membership;
use Illuminate\Support\Facades\Mail;

class OrganizationOwners
{
    public function email(int $organizationId, string $subject, string $text): void
    {
        $owners = Membership::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('key', 'owner'))
            ->with('user:id,email')
            ->get();

        foreach ($owners as $owner) {
            if ($owner->user?->email) {
                Mail::to($owner->user->email)->queue(new ClinicMessageMail($subject, $text));
            }
        }
    }
}
