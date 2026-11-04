<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedContact()
            ? redirect()->route('app.dashboard')
            : view('auth.verify');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->email) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Verification email sent. It can take a minute to arrive.');
    }

    public function verify(Request $request, string $id, string $hash, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user('web');

        // The signature proves the link is ours; this proves it is for this account.
        abort_unless((string) $user->getKey() === $id && hash_equals(sha1((string) $user->email), $hash), 403);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
            $audit->record('auth.email_verified', $user);
        }

        return redirect()->route('app.dashboard')->with('success', 'Email verified.');
    }
}
