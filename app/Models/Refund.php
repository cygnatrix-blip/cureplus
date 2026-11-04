<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $refunded_at
 */
class Refund extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = ['organization_id', 'invoice_id', 'payment_id', 'amount_paise', 'reason', 'status', 'gateway_refund_id', 'refunded_at', 'created_by'];

    protected function casts(): array
    {
        return ['refunded_at' => 'datetime', 'amount_paise' => 'integer'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
