<?php

namespace App\Http\Controllers\Patient;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\PatientAccount;
use App\Models\SecurityEvent;
use App\Rules\PassesCaptcha;
use App\Services\AuditLogger;
use App\Services\Auth\OtpService;
use App\Services\Portal\PatientRecords;
use App\Services\Portal\PortalBrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(): View
    {
        return view('patient.login');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'cf-turnstile-response' => [new PassesCaptcha],
        ]);

        $email = mb_strtolower(trim($data['email']));

        // A patient may be new to the portal, so a code goes to any valid address.
        // Nothing here reveals whether the address has records.
        $this->otp->sendToEmail(OtpPurpose::PatientLogin, $email);
        $request->session()->put('patient_login_email', $email);

        return redirect()->route('patient.login.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        return $request->session()->has('patient_login_email')
            ? view('patient.verify', ['email' => $request->session()->get('patient_login_email')])
            : redirect()->route('patient.login');
    }

    public function verify(Request $request, PatientRecords $records, AuditLogger $audit, PortalBrand $brand): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:'.config('cureplus.otp.length')]]);
        $email = (string) $request->session()->get('patient_login_email');

        if ($email === '' || ! $this->otp->verify(OtpPurpose::PatientLogin, $email, $data['code'])) {
            SecurityEvent::record('otp_failed', 'patient', $email);

            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        $account = PatientAccount::query()->firstOrCreate(['email' => $email], ['locale' => app()->getLocale()]);

        abort_unless($account->isActive(), 403);

        $request->session()->forget('patient_login_email');
        Auth::guard('patient')->login($account);
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now(), 'email_verified_at' => now()])->save();
        $records->link($account);
        $this->rememberOnlyClinic($account, $records, $brand);
        $audit->record('auth.patient_login', $account);

        return redirect()->intended(route('patient.home'));
    }

    public function destroy(Request $request, PortalBrand $brand): RedirectResponse
    {
        $current = $brand->current();

        Auth::guard('patient')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Which clinic's colours to show is not private, and the sign-in page should still look like theirs.
        if ($current !== null) {
            $brand->remember($current);
        }

        return redirect()->route('patient.login');
    }

    /**
     * Someone who signs in directly with one clinic on record sees that clinic's look.
     * With several clinics there is no right answer, so the neutral look stays.
     */
    private function rememberOnlyClinic(PatientAccount $account, PatientRecords $records, PortalBrand $brand): void
    {
        $clinics = $records->patients($account)->pluck('organization')->filter()->unique('id');

        if ($brand->current() === null && $clinics->count() === 1) {
            $brand->remember($clinics->first());
        }
    }
}
