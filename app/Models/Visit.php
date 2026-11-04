<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $visited_on
 * @property Carbon|null $completed_at
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'location_id', 'patient_id', 'doctor_user_id', 'appointment_id', 'visited_on',
        'complaints', 'history', 'diagnosis', 'notes', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
            'completed_at' => 'datetime',
            'complaints' => 'encrypted',
            'history' => 'encrypted',
            'diagnosis' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
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

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasOne<Vital, $this>
     */
    public function vital(): HasOne
    {
        return $this->hasOne(Vital::class);
    }

    /**
     * @return HasOne<Prescription, $this>
     */
    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    /**
     * @return HasMany<VisitAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(VisitAttachment::class);
    }
}
