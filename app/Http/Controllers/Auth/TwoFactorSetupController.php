<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Auth\LoginFlow;
use App\Services\Auth\OtpService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Shared by both portals; the URL prefix decides route names and layout.
 */
class TwoFactorSetupController extends Controller
{
    private const SESSION_SECRET = 'two_factor.setup_secret';

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly OtpService $otp,
        private readonly LoginFlow $login,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request): View
    {
        $account = $request->user();
        $recoveryCodes = $request->session()->get('two_factor.recovery_codes');

        if ($account->hasTwoFactorEnabled()) {
            return view('auth.two-factor-setup', [
                'portal' => $this->portal($request),
                'enabled' => true,
                'recoveryCodes' => $recoveryCodes,
                'secret' => null,
                'qr' => null,
            ]);
        }

        // Kept in the (encrypted) session until confirmed, so an abandoned
        // setup never leaves a half-active secret on the account.
        $secret = $request->session()->get(self::SESSION_SECRET) ?? $this->twoFactor->generateSecret();
        $request->session()->put(self::SESSION_SECRET, $secret);

        return view('auth.two-factor-setup', [
            'portal' => $this->portal($request),
            'enabled' => false,
            'recoveryCodes' => null,
            'secret' => $secret,
            'qr' => $this->twoFactor->qrSvg($account, $secret),
        ]);
    }

    public function confirmTotp(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $account = $request->user();
        $secret = (string) $request->session()->get(self::SESSION_SECRET);

        if ($secret === '' || ! $this->twoFactor->verifyTotp($account, $secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check the time on your phone.']);
        }

        return $this->enable($request, 'totp', $secret);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $this->login->sendTwoFactorCode($request->user());

        return back()->with('success', 'We sent you a code.');
    }

    public function confirmOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['otp_code' => ['required', 'digits:'.config('cureplus.otp.length')]]);
        $account = $request->user();

        if (! $this->otp->verify(OtpPurpose::TwoFactor, $this->login->twoFactorIdentifier($account), $data['otp_code'])) {
            throw ValidationException::withMessages(['otp_code' => 'That code is not valid.']);
        }

        return $this->enable($request, 'otp', null);
    }

    private function enable(Request $request, string $method, #[\SensitiveParameter] ?string $secret): RedirectResponse
    {
        $account = $request->user();

        $this->twoFactor->enable($account, $method, $secret);
        $this->audit->record('auth.two_factor_enabled', $account, ['method' => $method]);

        $request->session()->forget(self::SESSION_SECRET);
        // Shown once; flash keeps them out of the session after the next page.
        $request->session()->flash('two_factor.recovery_codes', $account->two_factor_recovery_codes);

        return redirect()->route($this->portal($request).'.two-factor.setup')
            ->with('success', 'Two-factor sign-in is on. Save your recovery codes now.');
    }

    private function portal(Request $request): string
    {
        return $request->is('admin/*') ? 'admin' : 'app';
    }
}
