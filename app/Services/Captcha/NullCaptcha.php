<?php

namespace App\Services\Captcha;

use App\Contracts\CaptchaVerifier;

class NullCaptcha implements CaptchaVerifier
{
    public function enabled(): bool
    {
        return false;
    }

    public function verify(?string $response, ?string $ip): bool
    {
        return true;
    }
}
