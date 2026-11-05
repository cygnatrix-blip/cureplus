<?php

namespace App\Models;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property AppointmentMode $mode
 * @property AppointmentStatus $status
 * @property VisitType $visit_type
 * @property Carbon $scheduled_on
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $pay_by
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'location_id', 'patient_id', 'doctor_user_id', 'kind', 'mode', 'video_link', 'pay_by', 'source', 'visit_type',
        'scheduled_on', 'starts_at', 'ends_at', 'slot_key', 'token_number', 'status', 'cancel_reason',
        'cancelled_at', 'checked_in_at', 'called_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => AppointmentMode::class,
            'status' => AppointmentStatus::class,
            'visit_type' => VisitType::class,
            'pay_by' => 'datetime',
            'scheduled_on' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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
     * @return HasOne<Visit, $this>
     */
    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    /**
     * A video call the patient booked themselves, held for them until they pay.
     */
    public function isAwaitingPayment(): bool
    {
        return $this->pay_by !== null && $this->status === AppointmentStatus::Booked;
    }

    public function isOnline(): bool
    {
        return $this->mode === AppointmentMode::Online;
    }

    /**
     * Whether the call can still happen, so a finished or cancelled one offers no way in.
     */
    public function canJoinVideo(): bool
    {
        return $this->isOnline() && $this->video_link !== null && ! $this->isAwaitingPayment() && $this->status->holdsSlot() && $this->status !== AppointmentStatus::Completed;
    }

    public static function slotKey(\DateTimeInterface $startsAt): string
    {
        return $startsAt->format('YmdHi');
    }
}
