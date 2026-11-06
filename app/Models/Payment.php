<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    use Auditable, BelongsToOrganization;

    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'razorpay' => 'Razorpay', 'cashfree' => 'Cashfree'];

    public const ONLINE_MODES = ['razorpay', 'cashfree'];

    /** A UPI payment the patient says they made, which staff have not yet found in their bank app. */
    public const UNVERIFIED = 'unverified';

    /** Staff could not find it. The transaction ID stays saved, so it cannot be sent again. */
    public const REJECTED = 'rejected';

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::UNVERIFIED => 'To check',
            self::REJECTED => 'Not received',
            default => ucfirst((string) $this->status),
        };
    }

    protected $fillable = [
        'organization_id', 'invoice_id', 'amount_paise', 'mode', 'status', 'reference', 'gateway_link_id',
        'gateway_link_url', 'gateway_payment_id', 'paid_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount_paise' => 'integer'];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refundedPaise(): int
    {
        return (int) Refund::withoutTenancy()->where('payment_id', $this->id)->where('status', '!=', 'failed')->sum('amount_paise');
    }
}
