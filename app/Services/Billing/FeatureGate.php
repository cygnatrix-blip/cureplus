<?php

namespace App\Services\Billing;

use App\Models\FeatureFlag;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;

class FeatureGate
{
    /** @var array<string, bool> */
    private array $resolved = [];

    /**
     * An override for this organization wins, then a rule for its plan, then what the plan
     * lists. An organization on no plan keeps everything, so a new feature cannot lock one out.
     */
    public function enabled(int $organizationId, string $feature): bool
    {
        return $this->resolved["{$organizationId}:{$feature}"] ??= $this->resolve($organizationId, $feature);
    }

    /**
     * @return list<string> Features this organization may not use
     */
    public function disabledRoutes(int $organizationId): array
    {
        $routes = [];

        foreach (config('plans.features') as $key => $feature) {
            if (! $this->enabled($organizationId, $key)) {
                $routes = [...$routes, ...$feature['routes']];
            }
        }

        return $routes;
    }

    private function resolve(int $organizationId, string $feature): bool
    {
        $override = FeatureFlag::withoutTenancy()->where('feature', $feature)->where('organization_id', $organizationId)->first();

        if ($override !== null) {
            return $override->is_enabled;
        }

        if (! config('plans.enabled')) {
            return true;
        }

        $plan = $this->planFor($organizationId);

        if ($plan === null) {
            return true;
        }

        $planRule = FeatureFlag::withoutTenancy()->where('feature', $feature)->whereNull('organization_id')->where('plan_code', $plan->code)->first();

        return $planRule !== null ? $planRule->is_enabled : (in_array('*', $plan->features, true) || in_array($feature, $plan->features, true));
    }

    private function planFor(int $organizationId): ?Plan
    {
        return OrganizationSubscription::withoutTenancy()->where('organization_id', $organizationId)->with('plan')->first()?->plan;
    }

    public function forget(): void
    {
        $this->resolved = [];
    }

    public function organizationHas(Organization $organization, string $feature): bool
    {
        return $this->enabled($organization->id, $feature);
    }
}
