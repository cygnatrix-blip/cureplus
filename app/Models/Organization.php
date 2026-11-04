<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Concerns\Auditable;
use App\Tenancy\OrganizationScope;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property OrganizationType $type
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $branding
 * @property OrganizationStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $rejected_at
 * @property Carbon|null $resubmitted_at
 * @property int|null $reviewed_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $suspended_at
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['type', 'name', 'slug', 'brand_color', 'gstin', 'settings'];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'status' => OrganizationStatus::class,
            'rejected_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            'settings' => 'array',
            'branding' => 'array',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * The numeric id stays the primary key; only this column is a UUID.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Pending and rejected practices can sign in and set up, but cannot reach real patients.
     */
    public function isAwaitingApproval(): bool
    {
        return in_array($this->status, [OrganizationStatus::PendingVerification, OrganizationStatus::Rejected], true);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Approved;
    }

    public function isSolo(): bool
    {
        return $this->type === OrganizationType::SoloPractice;
    }

    /**
     * Relations read from outside the tenant (platform admin), so they name the
     * organization explicitly instead of depending on the current context.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class)->withoutGlobalScope(OrganizationScope::class);
    }
}
