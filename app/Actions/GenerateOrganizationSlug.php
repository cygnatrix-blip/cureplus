<?php

namespace App\Actions;

use App\Models\Organization;
use Illuminate\Support\Str;

class GenerateOrganizationSlug
{
    /**
     * Soft-deleted organizations keep their slug, so an old QR poster can never
     * start pointing at a different clinic.
     */
    public function __invoke(string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'clinic';
        $slug = $base;

        for ($suffix = 2; $this->isTaken($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function isTaken(string $slug): bool
    {
        return in_array($slug, config('cureplus.reserved_slugs'), true)
            || Organization::withTrashed()->where('slug', $slug)->exists();
    }
}
