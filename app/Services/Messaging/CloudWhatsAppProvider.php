<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Http;

class CloudWhatsAppProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly string $phoneNumberId,
        #[\SensitiveParameter] private readonly string $token,
        private readonly string $language,
        private readonly string $apiVersion,
    ) {}

    /**
     * Asks Meta about this number, which proves the number and the token belong together.
     */
    public function testConnection(): bool
    {
        return Http::withToken($this->token)->timeout(15)->get("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}")->successful();
    }

    public function sendTemplate(string $to, string $template, array $parameters): string
    {
        $response = Http::withToken($this->token)
            ->timeout(15)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($to, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $this->language],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $parameters),
                    ]],
                ],
            ])
            ->throw();

        return (string) $response->json('messages.0.id');
    }
}
