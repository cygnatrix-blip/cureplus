<?php

namespace App\Contracts;

interface CaptchaVerifier
{
    public function enabled(): bool;

    public function verify(?string $response, ?string $ip): bool;
}
