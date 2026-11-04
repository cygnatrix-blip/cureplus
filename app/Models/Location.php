<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'address_line', 'city', 'state', 'pincode', 'phone', 'email',
        'timings', 'letterhead', 'rx_pad', 'is_active', 'accepts_online_booking',
    ];

    protected function casts(): array
    {
        return [
            'timings' => 'array',
            'letterhead' => 'array',
            'rx_pad' => 'array',
            'is_active' => 'boolean',
            'accepts_online_booking' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            $location->display_token ??= Str::random(40);
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
