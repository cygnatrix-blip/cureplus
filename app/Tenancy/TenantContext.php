<?php

namespace App\Tenancy;

use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use Closure;

/**
 * The organization the current request or job acts for.
 *
 * Tenant queries read this through OrganizationScope. With no tenant set they
 * return nothing, so a forgotten context fails closed instead of leaking data.
 */
class TenantContext
{
    private ?Organization $organization = null;

    private ?Location $location = null;

    private ?Membership $membership = null;

    public function set(Organization $organization, ?Membership $membership = null, ?Location $location = null): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
        $this->location = $location;
    }

    public function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    public function clear(): void
    {
        $this->organization = null;
        $this->membership = null;
        $this->location = null;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function location(): ?Location
    {
        return $this->location;
    }

    /**
     * Run a callback as a tenant (queued jobs, scheduled tasks), then restore the previous one.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Organization $organization, Closure $callback): mixed
    {
        [$previous, $membership, $location] = [$this->organization, $this->membership, $this->location];

        $this->set($organization);

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
            $this->membership = $membership;
            $this->location = $location;
        }
    }
}
