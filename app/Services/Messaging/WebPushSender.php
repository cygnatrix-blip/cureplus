<?php

namespace App\Services\Messaging;

use App\Contracts\PushSender;
use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

class WebPushSender implements PushSender
{
    public function __construct(private readonly ?ClientInterface $client = null) {}

    public function isConfigured(): bool
    {
        return ! blank(config('messages.push.public_key')) && ! blank(config('messages.push.private_key'));
    }

    public function send(PushSubscription $subscription, array $message): bool
    {
        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) (config('messages.push.subject') ?: config('app.url')),
            'publicKey' => (string) config('messages.push.public_key'),
            'privateKey' => (string) config('messages.push.private_key'),
        ]], ['TTL' => 3600], $this->client);

        $report = $webPush->sendOneNotification(
            Subscription::create(['endpoint' => $subscription->endpoint, 'publicKey' => $subscription->public_key, 'authToken' => $subscription->auth_token]),
            (string) json_encode($message),
        );

        return ! $report->isSubscriptionExpired();
    }
}
