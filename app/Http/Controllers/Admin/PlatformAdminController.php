<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.admins.index', [
            'admins' => PlatformAdmin::query()->orderBy('name')->paginate(25),
            'roles' => config('rbac.platform_roles'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('platform_admins', 'email')],
            'role' => ['required', Rule::in(array_keys(config('rbac.platform_roles')))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $admin = PlatformAdmin::create(['email' => mb_strtolower($data['email'])] + $data);
        $audit->record('platform_admins.created', $admin, ['role' => $admin->role]);

        return back()->with('success', 'Admin added. They must set up 2FA at first sign-in.');
    }

    /**
     * Sets a one-time temporary password and clears 2FA, so a locked-out admin can get back
     * in. Nobody can do this to themselves, which keeps it from being a way around 2FA.
     */
    public function resetPassword(Request $request, PlatformAdmin $admin, AuditLogger $audit): RedirectResponse
    {
        if ($admin->is($request->user())) {
            return back()->with('error', 'Another admin has to do this for you.');
        }

        $password = Str::password(20);
        $admin->forceFill([
            'password' => $password,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        DB::table('sessions')->where('platform_admin_id', $admin->id)->delete();
        $audit->record('platform_admins.password_reset', $admin);

        return back()->with('status', "Temporary password for {$admin->name}. Copy it now: {$password}");
    }

    public function deactivate(Request $request, PlatformAdmin $admin, AuditLogger $audit): RedirectResponse
    {
        if ($admin->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $admin->forceFill(['status' => 'inactive'])->save();
        DB::table('sessions')->where('platform_admin_id', $admin->id)->delete();
        $audit->record('platform_admins.deactivated', $admin);

        return back()->with('success', 'Admin deactivated.');
    }
}
