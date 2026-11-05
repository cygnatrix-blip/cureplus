<?php

namespace App\Models;

use App\Support\Specialties;
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

    protected $fillable = ['generic_name', 'brand_name', 'strength', 'form', 'manufacturer', 'system', 'specialty', 'is_active'];

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
        // Every word must match the start of a word, so "clav" finds Amoxicillin + Clavulanic Acid
        // and "para 650" finds the 650 mg strength.
        foreach (array_slice(array_filter(explode(' ', $term)), 0, 6) as $word) {
            $query->where(fn (Builder $query) => $query
                ->where('brand_name', 'like', "{$word}%")
                ->orWhere('brand_name', 'like', "% {$word}%")
                ->orWhere('generic_name', 'like', "{$word}%")
                ->orWhere('generic_name', 'like', "% {$word}%")
                ->orWhere('strength', 'like', "{$word}%"));
        }
    }

    /**
     * Allied health professionals see only what belongs to their own field. Everyone else
     * sees the whole catalogue.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithinScopeOf(Builder $query, ?string $specialty): void
    {
        if (Specialties::isAllied($specialty)) {
            $query->where(fn (Builder $query) => $query
                ->where('specialty', $specialty)
                ->orWhereIn('generic_name', config("specialties.allied_also_allowed.{$specialty}", [])));
        }
    }

    /**
     * Search results a doctor is likeliest to want come first: their own department, then
     * their own medical system, then names beginning with what they typed.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRankedFor(Builder $query, ?string $specialty, string $term = ''): void
    {
        // Quoted through the grammar because MySQL and MariaDB treat "system" as a keyword.
        $system = $query->getQuery()->getGrammar()->wrap('system');

        $query->orderByRaw("CASE WHEN specialty = ? THEN 0 WHEN {$system} = ? THEN 1 ELSE 2 END", [$specialty, Specialties::systemOf($specialty)])
            ->orderByRaw('CASE WHEN generic_name LIKE ? OR brand_name LIKE ? THEN 0 ELSE 1 END', ["{$term}%", "{$term}%"])
            ->orderBy('generic_name');
    }
}
