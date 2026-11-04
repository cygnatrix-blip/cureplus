<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginResult;
use App\Http\Controllers\Controller;
use App\Rules\PassesCaptcha;
use App\Services\Auth\LoginFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly LoginFlow $login) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'cf-turnstile-response' => [new PassesCaptcha],
        ]);

        [$result, $user] = $this->login->attemptPassword('web', $data['identifier'], $data['password']);

        return match ($result) {
            LoginResult::Success => $this->login->afterFirstFactor('web', $user, $request),
            LoginResult::Locked => throw ValidationException::withMessages(['identifier' => 'Too many attempts. Try again later.']),
            LoginResult::Invalid => throw ValidationException::withMessages(['identifier' => 'These details do not match our records.']),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        return $this->login->signOut('web', $request);
    }
}
