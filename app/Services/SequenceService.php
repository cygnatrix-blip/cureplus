<?php

namespace App\Services;

use App\Models\OrganizationCounter;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Gap-tolerant, never-repeating numbers per organization. The row lock
     * serialises concurrent callers, so two receptionists cannot get one token.
     */
    public function next(int $organizationId, string $key): int
    {
        return DB::transaction(function () use ($organizationId, $key): int {
            OrganizationCounter::withoutTenancy()->insertOrIgnore([
                'organization_id' => $organizationId,
                'counter_key' => $key,
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = OrganizationCounter::withoutTenancy()
                ->where('organization_id', $organizationId)
                ->where('counter_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            $next = $counter->value + 1;
            $counter->forceFill(['value' => $next])->save();

            return $next;
        });
    }
}
