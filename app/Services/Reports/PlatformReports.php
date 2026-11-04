<?php

namespace App\Services\Reports;

use App\Enums\OrganizationStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\PlatformInvoice;
use App\Models\Prescription;

/**
 * Platform-wide counts only: this page answers "how is the business" and
 * never shows a patient or a clinical value.
 */
class PlatformReports
{
    public function monthlyRecurringPaise(): int
    {
        return OrganizationSubscription::withoutTenancy()
            ->where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(fn (OrganizationSubscription $subscription) => $subscription->plan?->monthlyPaise() ?? 0);
    }

    /**
     * @return list<array{month: string, signups: int, revenue: int}>
     */
    public function lastTwelveMonths(): array
    {
        $rows = [];

        for ($back = 11; $back >= 0; $back--) {
            $start = now()->startOfMonth()->subMonths($back);
            $end = $start->copy()->endOfMonth();

            $rows[] = [
                'month' => $start->format('M Y'),
                'signups' => Organization::query()->whereBetween('created_at', [$start, $end])->count(),
                'revenue' => (int) PlatformInvoice::withoutTenancy()->where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('amount_paise'),
            ];
        }

        return $rows;
    }

    /**
     * @return array{active: int, cancelled: int, rate: float}
     */
    public function churnLastThirtyDays(): array
    {
        $cancelled = OrganizationSubscription::withoutTenancy()->where('cancelled_at', '>=', now()->subDays(30))->count();
        $active = OrganizationSubscription::withoutTenancy()->where('status', 'active')->count();
        $base = $active + $cancelled;

        return ['active' => $active, 'cancelled' => $cancelled, 'rate' => $base > 0 ? round($cancelled / $base * 100, 1) : 0.0];
    }

    /**
     * @return array<string, int>
     */
    public function featureAdoption(): array
    {
        $since = now()->subDays(30);

        return [
            'Appointments' => Appointment::withoutTenancy()->where('created_at', '>=', $since)->distinct()->count('organization_id'),
            'Prescriptions' => Prescription::withoutTenancy()->where('created_at', '>=', $since)->distinct()->count('organization_id'),
            'Billing' => Invoice::withoutTenancy()->where('created_at', '>=', $since)->distinct()->count('organization_id'),
            'Online booking' => Appointment::withoutTenancy()->where('source', 'link')->where('created_at', '>=', $since)->distinct()->count('organization_id'),
        ];
    }

    public function activeOrganizations(): int
    {
        return Organization::query()->where('status', OrganizationStatus::Approved)->count();
    }

    public function failedPayments(): int
    {
        return OrganizationSubscription::withoutTenancy()->where('status', 'past_due')->count();
    }
}
