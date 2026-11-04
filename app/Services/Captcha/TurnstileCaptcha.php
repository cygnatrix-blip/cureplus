<?php

namespace App\Services\Captcha;

use App\Contracts\CaptchaVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileCaptcha implements CaptchaVerifier
{
    public function enabled(): bool
    {
        return true;
    }

    public function verify(?string $response, ?string $ip): bool
    {
        if ($response === null || $response === '') {
            return false;
        }

        try {
            return (bool) Http::asForm()->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('cureplus.captcha.turnstile.secret_key'),
                    'response' => $response,
                    'remoteip' => $ip,
                ])
                ->json('success', false);
        } catch (ConnectionException) {
            return false;
        }
    }
}
