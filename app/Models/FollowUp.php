<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $due_on
 * @property Carbon|null $last_reminded_at
 */
class FollowUp extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = ['organization_id', 'patient_id', 'visit_id', 'doctor_user_id', 'due_on', 'status', 'call_notes', 'last_reminded_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'last_reminded_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }
}
