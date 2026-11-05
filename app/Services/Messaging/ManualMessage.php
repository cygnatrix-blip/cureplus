<?php

namespace App\Services\Messaging;

use App\Support\MessageText;

/**
 * For practices without a WhatsApp Business account or SMS gateway: staff open their own
 * WhatsApp or SMS app with the message already written, and press send themselves. The words
 * are the same ones the automatic messages use, so they stay free of clinical detail.
 */
class ManualMessage
{
    public function __construct(private readonly MessageWording $wording) {}

    /**
     * @param  array<string, string|int>  $variables
     */
    public function text(string $event, array $variables): string
    {
        return MessageText::render($this->wording->textFor($event, (string) config("messages.events.{$event}.text")), $variables);
    }

    public function whatsappUrl(string $mobile, string $text): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $mobile).'?text='.rawurlencode($text);
    }

    /**
     * "?&body=" is the one form that both Android and iPhone message apps read.
     */
    public function smsUrl(string $mobile, string $text): string
    {
        return 'sms:'.preg_replace('/[^\d+]/', '', $mobile).'?&body='.rawurlencode($text);
    }
}
