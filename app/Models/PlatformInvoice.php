<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $paid_at
 */
class PlatformInvoice extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'plan_id', 'number', 'amount_paise', 'status', 'razorpay_payment_id', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount_paise' => 'integer'];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
