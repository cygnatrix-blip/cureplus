<?php

namespace App\Services;

use App\Models\OrganizationIntegration;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class OrganizationMail
{
    /**
     * The organization's own SMTP if it set one up, otherwise the platform's mailer.
     */
    public function mailerFor(int $organizationId): Mailer
    {
        $integration = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', 'smtp')
            ->where('is_enabled', true)
            ->first();

        if ($integration === null) {
            return Mail::mailer();
        }

        return $this->build($integration->config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function build(array $config): Mailer
    {
        $mailer = Mail::build([
            'transport' => 'smtp',
            'host' => $config['host'] ?? 'smtp.gmail.com',
            'port' => (int) ($config['port'] ?? 587),
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'timeout' => 15,
        ]);

        if (! empty($config['from_address'])) {
            $mailer->alwaysFrom((string) $config['from_address'], (string) ($config['from_name'] ?? config('app.name')));
        }

        return $mailer;
    }
}
