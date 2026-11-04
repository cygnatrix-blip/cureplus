<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        // The same message either way, so this form cannot reveal who has an account.
        return back()->with('status', 'If that email has an account, a reset link is on its way.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request, UserSessions $sessions, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower($data['email']), 'token' => $data['token'], 'password' => $data['password']],
            function (User $user, string $password) use ($sessions, $audit): void {
                $user->forceFill(['password' => $password, 'failed_login_attempts' => 0, 'locked_until' => null])->saveQuietly();
                // Whoever made someone reset their password may still be signed in.
                $sessions->revokeAll($user);
                $audit->record('auth.password_reset', $user);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password updated. Please sign in.')
            : back()->withErrors(['email' => 'This reset link is invalid or has expired.']);
    }
}
