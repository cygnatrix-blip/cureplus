<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $fillable = ['organization_id', 'user_id', 'status', 'doctor_slug', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Bound to this membership by the pivot, so it needs no tenant scope. ResolveTenant
     * also loads it before a tenant exists, when the scope would return nothing.
     *
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'membership_location')->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string $key): bool
    {
        return $this->roles->contains('key', $key);
    }

    /**
     * Owners reach every location; everyone else only what was assigned.
     */
    public function canAccessLocation(int $locationId): bool
    {
        return $this->hasRole('owner') || $this->locations->contains('id', $locationId);
    }
}
