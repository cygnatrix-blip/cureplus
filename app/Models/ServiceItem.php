<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceItem extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $fillable = ['organization_id', 'name', 'price_paise', 'is_taxable', 'gst_rate_bp', 'sac_hsn_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_taxable' => 'boolean', 'is_active' => 'boolean', 'price_paise' => 'integer', 'gst_rate_bp' => 'integer'];
    }
}
