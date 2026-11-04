<?php

namespace App\Services\Billing;

use App\Enums\VisitType;
use App\Models\DoctorFee;
use App\Models\Organization;
use App\Models\Visit;

class FeeResolver
{
    /**
     * @return array{amount_paise: int, note: string|null}
     */
    public function resolve(Organization $organization, ?int $doctorId, VisitType $visitType, ?int $patientId): array
    {
        $fees = DoctorFee::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('visit_type', $visitType->value)
            ->where(fn ($query) => $query->where('doctor_user_id', $doctorId)->orWhereNull('doctor_user_id'))
            ->get();

        $amount = (int) ($fees->firstWhere('doctor_user_id', $doctorId)->amount_paise ?? $fees->first(fn (DoctorFee $fee) => $fee->doctor_user_id === null)->amount_paise ?? 0);

        $windowDays = (int) ($organization->settings['follow_up_window_days'] ?? 0);

        if ($visitType !== VisitType::FollowUp || $windowDays <= 0 || $patientId === null || $doctorId === null) {
            return ['amount_paise' => $amount, 'note' => null];
        }

        $inWindow = Visit::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('patient_id', $patientId)
            ->where('doctor_user_id', $doctorId)
            ->whereDate('visited_on', '>=', now()->subDays($windowDays)->toDateString())
            ->exists();

        if (! $inWindow) {
            return ['amount_paise' => $amount, 'note' => null];
        }

        $discount = max(0, min(100, (int) ($organization->settings['follow_up_discount_percent'] ?? 100)));

        return [
            'amount_paise' => (int) round($amount * (100 - $discount) / 100),
            'note' => "Follow-up within {$windowDays} days: {$discount}% off",
        ];
    }
}
