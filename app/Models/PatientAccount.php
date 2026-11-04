<?php

namespace App\Models;

use Database\Factories\PatientAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A patient's one login. Their clinical records stay separate per organization,
 * and only this account can see them side by side.
 *
 * @property Carbon|null $last_login_at
 */
class PatientAccount extends Authenticatable
{
    /** @use HasFactory<PatientAccountFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $attributes = ['status' => 'active', 'locale' => 'en'];

    protected $fillable = ['email', 'mobile', 'name', 'locale'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime', 'email_verified_at' => 'datetime'];
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
