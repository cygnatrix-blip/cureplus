<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorAvailability extends Model
{
    use BelongsToOrganization;

    protected $table = 'doctor_availability';

    /** Index 0 is Sunday, matching Carbon's dayOfWeek. */
    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = ['organization_id', 'location_id', 'doctor_user_id', 'weekday', 'start_time', 'end_time', 'slot_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weekday' => 'integer', 'slot_minutes' => 'integer'];
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
}
