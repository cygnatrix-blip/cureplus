<?php

namespace App\Services\Auth;

use App\Enums\LoginResult;
use App\Enums\OtpPurpose;
use App\Models\PlatformAdmin;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Mobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * The one path every login goes through, for both guards.
 */
class LoginFlow
{
    private const PENDING_KEY = 'two_factor.pending';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OtpService $otp,
    ) {}

    public function findAccount(string $guard, string $identifier): User|PlatformAdmin|null
    {
        if ($guard === 'platform') {
            return PlatformAdmin::query()->where('email', mb_strtolower(trim($identifier)))->first();
        }

        $mobile = Mobile::normalize($identifier);

        return $mobile !== null
            ? User::query()->where('mobile', $mobile)->first()
            : User::query()->where('email', mb_strtolower(trim($identifier)))->first();
    }

    /**
     * @return array{LoginResult, User|PlatformAdmin|null}
     */
    public function attemptPassword(string $guard, string $identifier, #[\SensitiveParameter] string $password): array
    {
        $account = $this->findAccount($guard, $identifier);

        if ($account?->isLocked()) {
            SecurityEvent::record('login_locked', $guard, $identifier, $account->getKey());

            return [LoginResult::Locked, null];
        }

        // Always hash, so a response time cannot reveal whether the account exists.
        $valid = Hash::check($password, $account->password ?? Hash::make('timing-equaliser'));

        if (! $account || ! $valid || ! $account->isActive()) {
            $account?->recordFailedLogin();
            SecurityEvent::record('login_failed', $guard, $identifier, $account?->getKey());

            return [LoginResult::Invalid, null];
        }

        return [LoginResult::Success, $account];
    }

    /**
     * First factor passed: continue to the 2FA screen, or sign in.
     */
    public function afterFirstFactor(string $guard, User|PlatformAdmin $account, Request $request): RedirectResponse
    {
        if (! $account->hasTwoFactorEnabled()) {
            return $this->signIn($guard, $account, $request);
        }

        // Regenerate here as well as at sign-in, so a planted session id never
        // reaches the state where it is one code away from being logged in.
        $request->session()->regenerate();
        $request->session()->put(self::PENDING_KEY, [
            'guard' => $guard,
            'id' => $account->getKey(),
            'expires_at' => now()->addMinutes(config('cureplus.security.two_factor_pending_minutes'))->getTimestamp(),
        ]);

        if (! $account->usesTotp()) {
            $this->sendTwoFactorCode($account);
        }

        return redirect()->route('two-factor.challenge');
    }

    public function sendTwoFactorCode(User|PlatformAdmin $account): void
    {
        $this->otp->sendToEmail(OtpPurpose::TwoFactor, $this->twoFactorIdentifier($account));
    }

    public function twoFactorIdentifier(User|PlatformAdmin $account): string
    {
        return (string) $account->email;
    }

    /**
     * @return array{string, User|PlatformAdmin}|null
     */
    public function pendingAccount(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);

        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp()) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        $account = $pending['guard'] === 'platform'
            ? PlatformAdmin::query()->find($pending['id'])
            : User::query()->find($pending['id']);

        return $account?->isActive() && ! $account->isLocked() ? [$pending['guard'], $account] : null;
    }

    public function signIn(string $guard, User|PlatformAdmin $account, Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_KEY);

        Auth::guard($guard)->login($account);
        $request->session()->regenerate();

        $account->recordSuccessfulLogin($request->ip());
        $this->audit->record('auth.login', $account, ['guard' => $guard]);

        return redirect()->intended(route($guard === 'platform' ? 'admin.dashboard' : 'app.dashboard'));
    }

    public function signOut(string $guard, Request $request): RedirectResponse
    {
        $account = Auth::guard($guard)->user();

        if ($account) {
            $this->audit->record('auth.logout', $account, ['guard' => $guard]);
        }

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($guard === 'platform' ? 'admin.login' : 'login');
    }
}
