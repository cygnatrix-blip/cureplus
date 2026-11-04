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
        return view('auth.register');
    }

    public function store(Request $request, RegisterOrganization $register, LoginFlow $login): RedirectResponse
    {
        // Normalised before validating, so the unique rule compares stored formats.
        $request->merge([
            'mobile' => Mobile::normalize((string) $request->input('mobile')) ?? $request->input('mobile'),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'type' => ['required', Rule::in(['clinic', 'solo_practice'])],
            'organization_name' => ['required_if:type,clinic', 'nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'mobile' => ['required', new IndianMobile, Rule::unique('users', 'mobile')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'cf-turnstile-response' => [new PassesCaptcha],
        ]);

        $user = $register($data);
        $user->sendEmailVerificationNotification();

        return $login->signIn('web', $user, $request);
    }
}
