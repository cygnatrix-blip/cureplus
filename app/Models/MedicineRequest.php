<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class MedicineRequest extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'requested_by', 'generic_name', 'brand_name', 'strength', 'form', 'manufacturer'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
