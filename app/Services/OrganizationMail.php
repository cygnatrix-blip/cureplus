<?php

namespace App\Services;

use App\Models\OrganizationIntegration;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class OrganizationMail
{
    /**
     * Email to a practice's patients goes out from the practice's own address, or not at all.
     * The platform's address is only for the platform's own business with practices, and for
     * sign-in and verification codes. It must never appear to be writing to a clinic's patients.
     */
    public function mailerFor(int $organizationId): ?Mailer
    {
        $integration = $this->integration($organizationId);

        return $integration === null ? null : $this->build($integration->config);
    }

    public function hasOwnEmail(int $organizationId): bool
    {
        return $this->integration($organizationId) !== null;
    }

    private function integration(int $organizationId): ?OrganizationIntegration
    {
        return OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', 'smtp')
            ->where('is_enabled', true)
            ->first();
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

        // Never the platform's address. Gmail also rewrites a From that is not the signed-in account.
        $from = $config['from_address'] ?? null ?: $config['username'] ?? null;

        if (! empty($from)) {
            $mailer->alwaysFrom((string) $from, (string) ($config['from_name'] ?? null ?: $from));
        }

        return $mailer;
    }
}
