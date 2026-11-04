<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property list<int>|null $organization_ids
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'organization_ids', 'starts_at', 'ends_at', 'published_by'];

    protected function casts(): array
    {
        return ['organization_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isFor(int $organizationId): bool
    {
        return $this->organization_ids === null || in_array($organizationId, $this->organization_ids, true);
    }
}
