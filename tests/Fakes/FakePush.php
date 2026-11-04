<?php

namespace Tests\Fakes;

use App\Contracts\PushSender;
use App\Models\PushSubscription;

class FakePush implements PushSender
{
    /** @var list<array{title: string, body: string, url: string}> */
    public array $sent = [];

    /** @var list<string> */
    public array $expired = [];

    public function isConfigured(): bool
    {
        return ! blank(config('messages.push.public_key'));
    }

    public function send(PushSubscription $subscription, array $message): bool
    {
        $this->sent[] = $message;

        return ! in_array($subscription->endpoint, $this->expired, true);
    }
}
