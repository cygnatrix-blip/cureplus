<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Auth\LoginFlow;
use App\Services\Auth\OtpService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly LoginFlow $login,
        private readonly OtpService $otp,
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $this->login->pendingAccount($request);

        return $pending ? view('auth.two-factor-challenge', ['usesTotp' => $pending[1]->usesTotp()]) : redirect()->route('login');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $pending = $this->login->pendingAccount($request);

        if ($pending === null) {
            return redirect()->route('login');
        }

        [$guard, $account] = $pending;

        if (! $this->codeIsValid($account, trim($data['code']))) {
            // Counts toward the account lockout: the 6-digit space is small.
            $account->recordFailedLogin();
            SecurityEvent::record('two_factor_failed', $guard, $account->email ?? $account->mobile, $account->getKey());

            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        return $this->login->signIn($guard, $account, $request);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->login->pendingAccount($request);

        if ($pending === null) {
            return redirect()->route('login');
        }

        if (! $pending[1]->usesTotp()) {
            $this->login->sendTwoFactorCode($pending[1]);
        }

        return back()->with('status', 'A new code is on its way.');
    }

    private function codeIsValid(User|PlatformAdmin $account, string $code): bool
    {
        if (str_contains($code, '-')) {
            return $this->twoFactor->consumeRecoveryCode($account, $code);
        }

        return $account->usesTotp()
            ? $this->twoFactor->verifyTotp($account, (string) $account->two_factor_secret, $code)
            : $this->otp->verify(OtpPurpose::TwoFactor, $this->login->twoFactorIdentifier($account), $code);
    }
}
