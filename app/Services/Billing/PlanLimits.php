<?php

namespace App\Services\Billing;

use App\Exceptions\PlanLimitException;
use App\Models\Location;
use App\Models\Membership;
use App\Models\MessageLog;
use App\Models\OrganizationSubscription;

class PlanLimits
{
    /**
     * @return array{doctors: int, staff: int, locations: int, messages_per_month: int}
     */
    public function usage(int $organizationId): array
    {
        $active = Membership::withoutTenancy()->where('organization_id', $organizationId)->where('status', 'active');

        return [
            'doctors' => (clone $active)->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))->count(),
            'staff' => (clone $active)->count(),
            'locations' => Location::withoutTenancy()->where('organization_id', $organizationId)->where('is_active', true)->count(),
            'messages_per_month' => $this->messagesThisMonth($organizationId),
        ];
    }

    public function limit(int $organizationId, string $key): ?int
    {
        $plan = OrganizationSubscription::withoutTenancy()->where('organization_id', $organizationId)->with('plan')->first()?->plan;

        return $plan?->limits[$key] ?? null;
    }

    /**
     * @throws PlanLimitException
     */
    public function assertRoomFor(int $organizationId, string $key, string $label): void
    {
        $limit = $this->limit($organizationId, $key);

        if ($limit !== null && $this->usage($organizationId)[$key] >= $limit) {
            throw new PlanLimitException("Your plan allows {$limit} {$label}. Upgrade to add more.");
        }
    }

    /**
     * SMS and WhatsApp cost money per message. Email and push do not, so they do not count.
     */
    public function canSendPaidMessage(int $organizationId): bool
    {
        $limit = $this->limit($organizationId, 'messages_per_month');

        return $limit === null || $this->messagesThisMonth($organizationId) < $limit;
    }

    private function messagesThisMonth(int $organizationId): int
    {
        return MessageLog::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->whereIn('channel', ['sms', 'whatsapp'])
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
