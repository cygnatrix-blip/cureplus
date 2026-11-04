<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppProvider;
use App\Models\OrganizationIntegration;

class WhatsAppFactory
{
    /**
     * The organization's own sender if it has one, otherwise the platform's, otherwise none.
     */
    public function forOrganization(?int $organizationId): ?WhatsAppProvider
    {
        $own = $organizationId === null ? null : OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', 'whatsapp')
            ->where('is_enabled', true)
            ->first();

        $config = config('messages.whatsapp');
        $phoneNumberId = $own->config['phone_number_id'] ?? $config['phone_number_id'];
        $token = $own->config['access_token'] ?? $config['token'];

        if (blank($phoneNumberId) || blank($token)) {
            return null;
        }

        return new CloudWhatsAppProvider((string) $phoneNumberId, (string) $token, (string) $config['language'], (string) $config['api_version']);
    }
}
