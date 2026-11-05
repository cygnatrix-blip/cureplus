<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterOrganization;
use App\Http\Controllers\Controller;
use App\Rules\IndianMobile;
use App\Rules\PassesCaptcha;
use App\Services\Auth\LoginFlow;
use App\Support\Mobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register', ['councils' => config('cureplus.medical_councils')]);
    }

    public function store(Request $request, RegisterOrganization $register, LoginFlow $login): RedirectResponse
    {
        // Normalised before validating, so the unique rule compares stored formats.
        $request->merge([
            'mobile' => Mobile::normalize((string) $request->input('mobile')) ?? $request->input('mobile'),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        // Everything needed for approval is asked for here, so nobody has to be called back for it.
        $isDoctor = $request->input('type') === 'solo_practice' || $request->boolean('owner_is_doctor');

        $data = $request->validate([
            'type' => ['required', Rule::in(['clinic', 'solo_practice'])],
            'organization_name' => ['required_if:type,clinic', 'nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'mobile' => ['required', new IndianMobile, Rule::unique('users', 'mobile')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'owner_is_doctor' => ['sometimes', 'boolean'],
            'registration_number' => [Rule::requiredIf($isDoctor), 'nullable', 'string', 'max:60'],
            'registration_council' => [Rule::requiredIf($isDoctor), 'nullable', 'string', 'max:120'],
            'qualification' => [Rule::requiredIf($isDoctor), 'nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:100'],
            // Checked from the file's contents. No SVG or HTML: they can carry script.
            'document' => [Rule::requiredIf($request->input('type') === 'clinic'), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'terms' => ['accepted'],
            'cf-turnstile-response' => [new PassesCaptcha],
        ], [
            'address_line.required' => 'Please enter your address. Patients see it, and we check it before approving you.',
            'city.required' => 'Please enter your city.',
            'pincode.regex' => 'Please enter a 6-digit PIN code.',
            'registration_number.required' => 'Please enter your medical registration number.',
            'registration_council.required' => 'Please enter your state medical council.',
            'qualification.required' => 'Please enter your qualification, for example MBBS, MD.',
            'document.required' => 'Please attach a clinic proof document.',
            'document.mimes' => 'Please attach a PDF, JPG or PNG file.',
            'document.max' => 'Please attach a file smaller than 5 MB.',
            'document.uploaded' => 'This file is too large for the server to accept. Please attach a smaller one.',
        ]);

        $user = $register($data);
        $user->sendEmailVerificationNotification();

        return $login->signIn('web', $user, $request);
    }
}
