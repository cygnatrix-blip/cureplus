<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property array<string, mixed>|null $dns_result
 * @property Carbon|null $dns_checked_at
 * @property Carbon|null $parked_at
 * @property Carbon|null $activated_at
 */
class CustomDomain extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'hostname', 'type', 'status', 'dns_result', 'dns_checked_at', 'ssl_status', 'parked_in_hosting', 'parked_at', 'activated_at'];

    protected function casts(): array
    {
        return ['dns_result' => 'array', 'dns_checked_at' => 'datetime', 'parked_in_hosting' => 'boolean', 'parked_at' => 'datetime', 'activated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // A domain that stops being active must stop serving straight away.
        static::saved(fn (self $domain) => Cache::forget('custom-domain.'.$domain->hostname));
        static::deleted(fn (self $domain) => Cache::forget('custom-domain.'.$domain->hostname));
    }
}
