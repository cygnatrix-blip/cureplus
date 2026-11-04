<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\BrandingService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        return view('app.staff.index', [
            'memberships' => Membership::query()
                ->with(['user:id,name,email,mobile', 'user.doctorProfile:id,user_id,verification_status,registration_number,registration_council', 'roles:id,key,name', 'locations:id,name'])
                ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'invitations' => Invitation::query()
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->latest('id')->limit(50)->get(),
            'roles' => $this->assignableRoles(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function edit(Membership $membership): View
    {
        return view('app.staff.edit', [
            'membership' => $membership->load(['user', 'roles', 'locations']),
            'roles' => $this->assignableRoles(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'key')],
            'locations' => ['array'],
            // Scoped rule: ids from another organization fail validation.
            'locations.*' => ['integer', Rule::exists('locations', 'id')->where('organization_id', $membership->organization_id)],
        ]);

        // A manager with "Manage staff" must not be able to create owners or change them.
        if (! $this->actorIsOwner() && (in_array('owner', $data['roles'], true) || $membership->roles()->where('key', 'owner')->exists())) {
            return back()->with('error', 'Only an owner can change an owner\'s access.');
        }

        if ($membership->roles()->where('key', 'owner')->exists() && ! in_array('owner', $data['roles'], true) && $this->ownerCount() === 1) {
            return back()->with('error', 'An organization must keep at least one owner.');
        }

        $membership->roles()->sync(Role::query()->whereIn('key', $data['roles'])->pluck('id'));

        if (in_array('doctor', $data['roles'], true) && $membership->doctor_slug === null) {
            $membership->doctor_slug = Membership::uniqueDoctorSlug((int) $membership->organization_id, (string) $membership->user?->name);
            $membership->save();
        }

        $membership->locations()->sync($data['locations'] ?? []);
        app(AuditLogger::class)->record('memberships.access_changed', $membership, [
            'roles' => $data['roles'],
            'locations' => $data['locations'] ?? [],
        ]);

        return redirect()->route('app.staff.index')->with('success', 'Access updated.');
    }

    public function photo(Request $request, Membership $membership, BrandingService $branding): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120']], [
            'photo.uploaded' => 'This file is too large for the server to accept. Please choose a smaller one.',
            'photo.mimes' => 'Please use a PNG or JPG image.',
        ]);

        abort_unless($membership->hasRole('doctor'), 404);

        $branding->saveDoctorPhoto(DoctorProfile::query()->firstOrCreate(['user_id' => $membership->user_id]), $request->file('photo'));

        return back()->with('success', 'Photo saved.');
    }

    public function deactivate(Membership $membership): RedirectResponse
    {
        if (! $this->actorIsOwner() && $membership->roles()->where('key', 'owner')->exists()) {
            return back()->with('error', 'Only an owner can change an owner\'s access.');
        }

        if ($membership->roles()->where('key', 'owner')->exists() && $this->ownerCount() === 1) {
            return back()->with('error', 'An organization must keep at least one owner.');
        }

        $membership->update(['status' => 'inactive']);

        return back()->with('success', 'Access removed.');
    }

    public function activate(Membership $membership): RedirectResponse
    {
        $membership->update(['status' => 'active']);

        return back()->with('success', 'Access restored.');
    }

    private function actorIsOwner(): bool
    {
        return app(TenantContext::class)->membership()?->hasRole('owner') ?? false;
    }

    /**
     * @return Collection<int, Role>
     */
    private function assignableRoles(): Collection
    {
        return Role::query()->when(! $this->actorIsOwner(), fn ($query) => $query->where('key', '!=', 'owner'))->orderBy('id')->get();
    }

    private function ownerCount(): int
    {
        return Membership::query()->active()->whereHas('roles', fn ($q) => $q->where('key', 'owner'))->count();
    }
}
