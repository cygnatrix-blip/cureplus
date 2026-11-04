<?php

namespace App\Models;

use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global master list, shared by every organization.
 */
class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use HasFactory;

    protected $fillable = ['generic_name', 'brand_name', 'strength', 'form', 'manufacturer', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function displayName(): string
    {
        return trim(($this->brand_name ?: $this->generic_name).' '.$this->strength);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        // Prefix match, so the index can serve it as the catalogue grows.
        $query->where(fn (Builder $query) => $query
            ->where('brand_name', 'like', "{$term}%")
            ->orWhere('generic_name', 'like', "{$term}%"));
    }
}
