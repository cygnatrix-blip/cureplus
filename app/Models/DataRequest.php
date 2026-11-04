<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A patient's DPDP request: correction or deletion.
 *
 * @property Carbon|null $resolved_at
 */
class DataRequest extends Model
{
    use Auditable;

    protected $fillable = ['patient_account_id', 'type', 'details'];

    protected function casts(): array
    {
        return ['details' => 'encrypted', 'resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<PatientAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PatientAccount::class, 'patient_account_id');
    }
}
