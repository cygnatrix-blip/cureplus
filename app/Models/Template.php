<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform defaults (no organization) plus each organization's own.
 */
class Template extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'kind', 'specialty', 'name', 'template_key', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['body' => 'array', 'is_active' => 'boolean'];
    }

    public function allowsNullOrganization(): bool
    {
        return true;
    }

    /**
     * What the current tenant may use: its own templates and the platform's.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAvailable(Builder $query, string $kind): void
    {
        $query->withoutTenancy()
            ->where('kind', $kind)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('organization_id')
                ->orWhere('organization_id', app(TenantContext::class)->id()));
    }
}
