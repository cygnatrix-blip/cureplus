<?php

namespace App\Http\Controllers\App;

use App\Actions\InviteMember;
use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Membership;
use App\Services\Billing\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function store(Request $request, TenantContext $tenant, InviteMember $invite, PlanLimits $limits): RedirectResponse
    {
        try {
            $limits->assertRoomFor((int) $tenant->id(), 'staff', 'team members');

            if (in_array('doctor', (array) $request->input('role_keys'), true)) {
                $limits->assertRoomFor((int) $tenant->id(), 'doctors', 'doctors');
            }
        } catch (PlanLimitException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role_keys' => ['required', 'array', 'min:1'],
            'role_keys.*' => ['string', Rule::exists('roles', 'key')],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['integer', Rule::exists('locations', 'id')->where('organization_id', $tenant->id())],
        ]);

        $data['email'] = mb_strtolower($data['email']);

        // Otherwise anyone with "Manage staff" could invite a friend, or themselves, as an owner.
        if (in_array('owner', $data['role_keys'], true) && ! $tenant->membership()?->hasRole('owner')) {
            throw ValidationException::withMessages(['role_keys' => 'Only an owner can add another owner.']);
        }

        $alreadyMember = Membership::query()
            ->whereHas('user', fn ($q) => $q->where('email', $data['email']))
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => 'This person is already on your staff list.']);
        }

        // Only the newest link works, so an earlier one sent to the wrong person can be replaced.
        Invitation::query()->where('email', $data['email'])->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $invite($tenant->organization(), $request->user(), $data);

        return back()->with('success', "Invitation sent to {$data['email']}. The link works for ".config('cureplus.invitations.ttl_days').' days.');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Invitation cancelled.');
    }
}
