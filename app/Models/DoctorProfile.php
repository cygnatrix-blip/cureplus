<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $verified_at
 * @property string|null $photo_path
 * @property string|null $verification_note
 */
class DoctorProfile extends Model
{
    protected $fillable = ['user_id', 'qualification', 'specialty', 'registration_number', 'registration_council'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
