<?php

namespace App\Tenancy;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Required on every model whose table has organization_id.
 * TenantIsolationTest fails if a table is added without it.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            $tenantId = app(TenantContext::class)->id();

            if ($model->organization_id === null) {
                $model->organization_id = $tenantId;
            }

            if ($model->organization_id === null && ! $model->allowsNullOrganization()) {
                throw new LogicException('Cannot create '.static::class.' without an organization.');
            }

            // A request acting for one tenant must never write into another.
            if ($tenantId !== null && $model->organization_id !== null && (int) $model->organization_id !== $tenantId && ! $model->allowsWriteAcrossOrganizations()) {
                throw new LogicException('Cannot create '.static::class.' for a different organization.');
            }
        });

        static::updating(function ($model): void {
            if ($model->isDirty('organization_id')) {
                throw new LogicException('A record cannot move between organizations.');
            }
        });
    }

    /**
     * Explicit, greppable opt-out for platform-level code only.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OrganizationScope::class);
    }

    public function allowsNullOrganization(): bool
    {
        return false;
    }

    public function allowsWriteAcrossOrganizations(): bool
    {
        return false;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
