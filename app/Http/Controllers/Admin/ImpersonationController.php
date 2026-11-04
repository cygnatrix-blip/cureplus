<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTenant;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, Organization $organization, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $admin = $request->user('platform');

        $membership = $organization->memberships()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('key', 'owner'))
            ->with('user')
            ->first();

        if ($membership === null || $membership->user === null) {
            return back()->with('error', 'This organization has no active owner to view as.');
        }

        // Logged by the admin's own identity, before the session changes.
        $audit->record('impersonation.started', $organization, ['reason' => $data['reason']]);

        Auth::guard('web')->login($membership->user);
        $request->session()->regenerate();
        $request->session()->put('impersonation', ['admin_id' => $admin->id, 'organization_uuid' => $organization->uuid]);
        $request->session()->put(ResolveTenant::SESSION_ORGANIZATION, $organization->id);
        $request->session()->forget(ResolveTenant::SESSION_LOCATION);

        return redirect()->route('app.dashboard');
    }

    public function stop(Request $request, AuditLogger $audit): RedirectResponse
    {
        $uuid = $request->session()->get('impersonation.organization_uuid');
        $organization = $uuid ? Organization::query()->where('uuid', $uuid)->first() : null;

        if ($organization) {
            $audit->record('impersonation.stopped', $organization);
        }

        Auth::guard('web')->logout();
        $request->session()->forget(['impersonation', ResolveTenant::SESSION_ORGANIZATION, ResolveTenant::SESSION_LOCATION]);
        $request->session()->regenerate();

        return redirect()->route($organization ? 'admin.organizations.show' : 'admin.dashboard', $organization ? [$organization] : []);
    }
}
