<?php

namespace App\Http\Controllers\App;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTenant;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SwitcherController extends Controller
{
    public function index(Request $request): View
    {
        return view('app.organizations', [
            'memberships' => $request->user()->memberships()->active()->with(['organization', 'roles'])->get()
                ->filter(fn (Membership $membership) => $membership->organization !== null),
            'activeId' => $request->session()->get(ResolveTenant::SESSION_ORGANIZATION),
        ]);
    }

    public function switchOrganization(Request $request, string $uuid): RedirectResponse
    {
        abort_if($request->session()->has('impersonation.admin_id'), 403);

        // Looked up through the user's own memberships: another organization's uuid is a 404.
        $organization = Organization::query()->where('uuid', $uuid)->firstOrFail();
        $membership = $request->user()->memberships()->active()->where('organization_id', $organization->id)->firstOrFail();

        $request->session()->put(ResolveTenant::SESSION_ORGANIZATION, $membership->organization_id);
        $request->session()->forget(ResolveTenant::SESSION_LOCATION);

        return redirect()->route('app.dashboard');
    }

    public function switchLocation(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate(['location_id' => ['required', 'integer']]);
        $location = Location::query()->active()->findOrFail($data['location_id']);

        abort_unless($tenant->membership()?->canAccessLocation($location->id), 403);

        $request->session()->put(ResolveTenant::SESSION_LOCATION, $location->id);

        return back();
    }

    public function none(): View
    {
        return view('app.no-organization');
    }

    public function status(Request $request): View|RedirectResponse
    {
        $organization = $request->user()->memberships()->active()
            ->where('organization_id', $request->session()->get(ResolveTenant::SESSION_ORGANIZATION))
            ->with('organization')->first()?->organization;

        if ($organization === null || ($organization->status !== OrganizationStatus::Suspended && ! $organization->accessHasEnded())) {
            return redirect()->route('app.dashboard');
        }

        return view('app.organization-status', ['organization' => $organization]);
    }
}
