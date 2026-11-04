<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\LoginFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationAcceptController extends Controller
{
    public function __construct(private readonly LoginFlow $login, private readonly AcceptInvitation $accept) {}

    public function show(string $token, Request $request): View|RedirectResponse
    {
        $invitation = $this->findPending($token);
        $existing = $this->existingAccount($invitation);

        if ($existing && Auth::guard('web')->id() !== $existing->id) {
            $request->session()->put('url.intended', route('invitations.show', $token));

            return redirect()->route('login')->with('status', 'Sign in to accept your invitation.');
        }

        return view('auth.invitation', [
            'invitation' => $invitation,
            'organization' => Organization::query()->findOrFail($invitation->organization_id),
            'token' => $token,
            'existing' => $existing,
        ]);
    }

    public function accept(string $token, Request $request): RedirectResponse
    {
        $invitation = $this->findPending($token);
        $existing = $this->existingAccount($invitation);

        if ($existing) {
            // Holding the link is not enough for an existing account: otherwise a
            // forwarded email would let someone act on another person's identity.
            abort_unless(Auth::guard('web')->id() === $existing->id, 403);
            ($this->accept)($invitation, $existing);

            return redirect()->route('app.dashboard')->with('success', 'Invitation accepted.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $invitation->email,
            'password' => $data['password'],
            'terms_accepted_at' => now(),
        ]);

        // The link reached them on this channel, which proves they control it.
        $user->forceFill([
            'email_verified_at' => now(),
        ])->saveQuietly();

        ($this->accept)($invitation, $user);

        return $this->login->signIn('web', $user, $request);
    }

    private function findPending(string $token): Invitation
    {
        $invitation = Invitation::withoutTenancy()->where('token_hash', Invitation::hashToken($token))->first();

        abort_unless($invitation?->isPending(), 404);

        return $invitation;
    }

    private function existingAccount(Invitation $invitation): ?User
    {
        return User::query()
            ->where('email', $invitation->email)
            ->first();
    }
}
