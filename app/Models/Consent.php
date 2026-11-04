<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $granted_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
class Consent extends Model
{
    public const CROSS_ORG_HISTORY = 'cross_org_history';

    public const BOOKING_AND_CARE = 'booking_and_care';

    protected $fillable = ['patient_account_id', 'purpose', 'grantee_organization_id', 'grantee_doctor_user_id', 'granted_via', 'granted_at', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function granteeOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'grantee_organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function granteeDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantee_doctor_user_id');
    }

    /**
     * @return BelongsTo<PatientAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PatientAccount::class, 'patient_account_id');
    }
}
