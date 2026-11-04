<?php

namespace App\Contracts;

interface SmsProvider
{
    public function send(string $mobile, #[\SensitiveParameter] string $message): void;
}
