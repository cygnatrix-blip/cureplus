<?php

namespace App\Services\Messaging;

use App\Support\MessageText;
use Illuminate\Support\Facades\Crypt;

/**
 * For practices without a WhatsApp Business account or SMS gateway: staff open their own
 * WhatsApp or SMS app with the message already written, and press send themselves. The words
 * are the same ones the automatic messages use, so they stay free of clinical detail.
 */
class ManualMessage
{
    private const TOKEN_MINUTES = 240;

    public function __construct(private readonly MessageWording $wording, private readonly WhatsAppFactory $whatsApp) {}

    /**
     * Asked once per page, because a list shows this button on every row.
     */
    public function hasWhatsAppBusiness(int $organizationId): bool
    {
        $key = "whatsapp-business-{$organizationId}";

        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, $this->whatsApp->forOrganization($organizationId) !== null);
        }

        return (bool) request()->attributes->get($key);
    }

    /**
     * What the WhatsApp Business button posts. Encrypted, so the words and the recipient are
     * the ones on the page: staff cannot be tricked into sending anything else.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public function sendToken(int $organizationId, int $patientId, string $event, array $variables): string
    {
        return Crypt::encrypt(['organization_id' => $organizationId, 'patient_id' => $patientId, 'event' => $event, 'variables' => $variables, 'expires' => now()->addMinutes(self::TOKEN_MINUTES)->timestamp]);
    }

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
