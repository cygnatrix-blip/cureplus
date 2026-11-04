<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['feature', 'organization_id', 'plan_code', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function allowsNullOrganization(): bool
    {
        return true;
    }
}
