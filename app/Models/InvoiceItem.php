<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'invoice_id', 'service_item_id', 'description', 'sac_hsn_code', 'quantity', 'unit_paise',
        'discount_paise', 'is_taxable', 'gst_rate_bp', 'tax_paise', 'line_total_paise',
    ];

    protected function casts(): array
    {
        return ['is_taxable' => 'boolean', 'quantity' => 'integer', 'unit_paise' => 'integer', 'discount_paise' => 'integer', 'gst_rate_bp' => 'integer', 'tax_paise' => 'integer', 'line_total_paise' => 'integer'];
    }
}
