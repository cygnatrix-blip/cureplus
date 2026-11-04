<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Development only. It writes messages, including OTPs, to the log, so it
 * refuses to run in production instead of quietly leaking login codes there.
 */
class LogSmsProvider implements SmsProvider
{
    public function send(string $mobile, #[\SensitiveParameter] string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SMS_DRIVER=log is not allowed in production.');
        }

        Log::info('SMS', ['to' => $mobile, 'message' => $message]);
    }
}
