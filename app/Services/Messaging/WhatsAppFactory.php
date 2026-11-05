<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppProvider;
use App\Models\OrganizationIntegration;

class WhatsAppFactory
{
    /**
     * With an organization: its own WhatsApp Business number, or nothing. Its patients must never
     * hear from the platform's number, just as they never get email from the platform's address.
     * Without one: the platform's own number, for writing to practice owners.
     */
    public function forOrganization(?int $organizationId): ?WhatsAppProvider
    {
        if ($organizationId === null) {
            return $this->make(config('messages.whatsapp.phone_number_id'), config('messages.whatsapp.token'));
        }

        $own = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', 'whatsapp')
            ->where('is_enabled', true)
            ->first();

        return $own === null ? null : $this->fromIntegration($own);
    }

    public function fromIntegration(OrganizationIntegration $integration): ?CloudWhatsAppProvider
    {
        return $this->make($integration->config['phone_number_id'] ?? null, $integration->config['access_token'] ?? null);
    }

    private function make(mixed $phoneNumberId, mixed $token): ?CloudWhatsAppProvider
    {
        if (blank($phoneNumberId) || blank($token)) {
            return null;
        }

        return new CloudWhatsAppProvider((string) $phoneNumberId, (string) $token, (string) config('messages.whatsapp.language'), (string) config('messages.whatsapp.api_version'));
    }
}
