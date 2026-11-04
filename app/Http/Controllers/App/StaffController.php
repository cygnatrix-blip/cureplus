<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Role;
use App\Services\AuditLogger;
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
                ->with(['user:id,name,email,mobile', 'roles:id,key,name', 'locations:id,name'])
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
            'roles' => Role::query()->orderBy('id')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function edit(Membership $membership): View
    {
        return view('app.staff.edit', [
            'membership' => $membership->load(['user', 'roles', 'locations']),
            'roles' => Role::query()->orderBy('id')->get(),
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

        if ($membership->roles()->where('key', 'owner')->exists() && ! in_array('owner', $data['roles'], true) && $this->ownerCount() === 1) {
            return back()->with('error', 'An organization must keep at least one owner.');
        }

        $membership->roles()->sync(Role::query()->whereIn('key', $data['roles'])->pluck('id'));
        $membership->locations()->sync($data['locations'] ?? []);
        app(AuditLogger::class)->record('memberships.access_changed', $membership, [
            'roles' => $data['roles'],
            'locations' => $data['locations'] ?? [],
        ]);

        return redirect()->route('app.staff.index')->with('success', 'Access updated.');
    }

    public function deactivate(Membership $membership): RedirectResponse
    {
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

    private function ownerCount(): int
    {
        return Membership::query()->active()->whereHas('roles', fn ($q) => $q->where('key', 'owner'))->count();
    }
}
