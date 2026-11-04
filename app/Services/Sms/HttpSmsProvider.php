<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;

/**
 * Generic JSON gateway. A provider with its own request format gets its own
 * class behind SmsProvider and one line in AppServiceProvider.
 */
class HttpSmsProvider implements SmsProvider
{
    public function send(string $mobile, #[\SensitiveParameter] string $message): void
    {
        $config = config('cureplus.sms.http');

        Http::timeout($config['timeout'])
            ->withToken((string) $config['token'])
            ->post((string) $config['url'], [
                'to' => $mobile,
                'sender' => $config['sender'],
                'message' => $message,
            ])
            ->throw();
    }
}
