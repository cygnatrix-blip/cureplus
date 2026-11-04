<?php

namespace App\Contracts;

use App\Models\PushSubscription;

interface PushSender
{
    public function isConfigured(): bool;

    /**
     * @param  array{title: string, body: string, url: string}  $message
     * @return bool False when the browser has dropped this subscription for good.
     */
    public function send(PushSubscription $subscription, array $message): bool;
}
