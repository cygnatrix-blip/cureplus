<?php

namespace App\Http\Controllers\App;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\Auth\OtpService;
use App\Services\Portal\CrossOrganizationHistory;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PatientHistoryController extends Controller
{
    public function __construct(private readonly CrossOrganizationHistory $history, private readonly TenantContext $tenant) {}

    public function show(Request $request, Patient $patient): View
    {
        $user = $request->user();
        $organization = $this->tenant->organization();

        return view('app.patients.history', [
            'patient' => $patient,
            'hasConsent' => $this->history->hasConsent($patient, $user, $organization),
            'visits' => $this->history->visits($patient, $user, $organization),
        ]);
    }

    public function requestConsent(Patient $patient, OtpService $otp): RedirectResponse
    {
        if ($patient->email === null) {
            return back()->with('error', 'Add this patient\'s email first. The code goes there, so only they can agree.');
        }

        $otp->sendToEmail(OtpPurpose::ConsentShare, $patient->email);

        return back()->with('success', 'A code was emailed to the patient. Ask them to read it to you if they agree to share.');
    }

    public function confirmConsent(Request $request, Patient $patient, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:'.config('cureplus.otp.length')]]);

        if ($patient->email === null || ! $otp->verify(OtpPurpose::ConsentShare, $patient->email, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        $this->history->grant($patient, $request->user(), $this->tenant->organization());

        return back()->with('success', 'The patient agreed to share their records with you.');
    }
}
