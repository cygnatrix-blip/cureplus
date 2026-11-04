<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $grace_ends_at
 * @property Carbon|null $cancelled_at
 */
class OrganizationSubscription extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'plan_id', 'status', 'trial_ends_at', 'current_period_ends_at', 'grace_ends_at', 'razorpay_subscription_id', 'checkout_url', 'coupon_id', 'cancelled_at'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
