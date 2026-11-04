<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly UserSessions $sessions, private readonly AuditLogger $audit) {}

    public function show(Request $request): View
    {
        return view('app.account', [
            'user' => $request->user(),
            'sessions' => $this->sessions->forUser($request->user(), $request->session()->getId()),
            'isDoctor' => $request->user()->memberships()->active()->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))->exists(),
            'doctorProfile' => $request->user()->doctorProfile,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->saveQuietly();
        $this->sessions->revokeAll($request->user(), $request->session()->getId());
        $request->session()->regenerate();
        $this->audit->record('auth.password_changed', $request->user());

        return back()->with('success', 'Password changed. Your other devices have been signed out.');
    }

    public function revokeSession(Request $request, string $token): RedirectResponse
    {
        if ($this->sessions->revoke($request->user(), $token)) {
            $this->audit->record('auth.session_revoked', $request->user());
        }

        return back()->with('success', 'Device signed out.');
    }
}
