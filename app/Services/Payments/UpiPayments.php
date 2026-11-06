<?php

namespace App\Services\Payments;

use App\Enums\AppointmentMode;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Support\Money;

/**
 * Direct UPI for practices without Razorpay or Cashfree. The patient pays the practice's own
 * UPI ID and types the transaction ID, which nobody can check automatically, so staff confirm
 * each payment against their bank app before it counts.
 */
class UpiPayments
{
    public const KIND = 'upi';

    /** What a practice asks of patients who book themselves, per kind of appointment. */
    public const RULES = [
        'off' => 'Do not ask',
        'optional' => 'Patients may pay when booking',
        'required' => 'Patients must pay to confirm',
    ];

    public function __construct(private readonly GatewayFactory $gateways) {}

    /**
     * Null when UPI is not on. A connected gateway wins, because it confirms payments itself.
     *
     * @return array{vpa: string, payee_name: string, online: string, in_person: string}|null
     */
    public function settingsFor(int $organizationId): ?array
    {
        if (! Organization::query()->find($organizationId)?->isActive() || $this->gateways->forOrganization($organizationId) !== null) {
            return null;
        }

        $config = OrganizationIntegration::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('kind', self::KIND)
            ->where('is_enabled', true)
            ->first()->config ?? [];

        if (blank($config['vpa'] ?? null)) {
            return null;
        }

        return [
            'vpa' => (string) $config['vpa'],
            'payee_name' => (string) ($config['payee_name'] ?? ''),
            'online' => $this->rule($config['online'] ?? null),
            'in_person' => $this->rule($config['in_person'] ?? null),
        ];
    }

    public function ruleFor(int $organizationId, AppointmentMode $mode): string
    {
        return $this->settingsFor($organizationId)[$mode->value] ?? 'off';
    }

    /**
     * The link every UPI app understands. The amount and note are filled in, so the patient
     * cannot pay the wrong amount and staff can find the payment in their statement.
     *
     * @param  array{vpa: string, payee_name: string}  $settings
     */
    public function link(array $settings, int $amountPaise, string $note): string
    {
        return 'upi://pay?'.http_build_query([
            'pa' => $settings['vpa'],
            'pn' => $settings['payee_name'],
            'am' => Money::decimal($amountPaise),
            'cu' => 'INR',
            'tn' => $note,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * UPI transaction IDs (UTR) are 12 digits. Apps show them with spaces, so those are dropped.
     */
    public static function normalizeUtr(string $value): ?string
    {
        $digits = preg_replace('/[\s-]/', '', $value) ?? '';

        return preg_match('/^\d{12}$/', $digits) === 1 ? $digits : null;
    }

    private function rule(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::RULES) ? $value : 'off';
    }
}
