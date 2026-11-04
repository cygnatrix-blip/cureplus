<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Rules\PassesCaptcha;
use App\Services\Auth\LoginFlow;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * For staff without 2FA. Owners and doctors must use a password plus 2FA, because
 * an email code followed by an email 2FA code would really be one factor twice.
 */
class OtpLoginController extends Controller
{
    public function __construct(private readonly LoginFlow $login, private readonly OtpService $otp) {}

    public function show(): View
    {
        return view('auth.login-otp');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'cf-turnstile-response' => [new PassesCaptcha],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $account = User::query()->where('email', $email)->first();

        if ($account && $this->isEligible($account)) {
            $this->otp->sendToEmail(OtpPurpose::Login, $email);
        }

        // The same response whatever happened, so this form cannot reveal who has an account.
        $request->session()->put('login_otp_email', $email);

        return redirect()->route('login.otp.verify')->with('status', 'If this email can sign in with a code, we have sent one.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        return $request->session()->has('login_otp_email') ? view('auth.login-otp-verify') : redirect()->route('login.otp');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:'.config('cureplus.otp.length')]]);
        $email = (string) $request->session()->get('login_otp_email');
        $account = $email !== '' ? User::query()->where('email', $email)->first() : null;

        // Eligibility is checked again: the account may have changed since the code was sent.
        if (! $account || ! $this->isEligible($account) || ! $this->otp->verify(OtpPurpose::Login, $email, $data['code'])) {
            SecurityEvent::record('otp_failed', 'web', $email, $account?->getKey());

            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        $request->session()->forget('login_otp_email');
        $account->forceFill(['email_verified_at' => $account->email_verified_at ?? now()])->saveQuietly();

        return $this->login->signIn('web', $account, $request);
    }

    private function isEligible(User $account): bool
    {
        return $account->isActive() && ! $account->isLocked() && ! $account->requiresTwoFactor() && ! $account->hasTwoFactorEnabled();
    }
}
