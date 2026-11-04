<?php

namespace App\Models;

use App\Models\Concerns\HasLoginProtection;
use App\Models\Concerns\HasTwoFactor;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Tenancy\OrganizationScope;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $locked_until
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property list<string>|null $two_factor_recovery_codes
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasLoginProtection, HasTwoFactor, Notifiable, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected $fillable = ['name', 'email', 'mobile', 'password', 'terms_accepted_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    private ?bool $requiresTwoFactorCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /**
     * Deliberately ignores the tenant scope: a person's own memberships span
     * organizations, and this is what the organization switcher reads.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return HasOne<DoctorProfile, $this>
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->requiresTwoFactorCache ??= $this->memberships()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('key', config('cureplus.security.two_factor_roles')))
            ->exists();
    }

    public function hasVerifiedContact(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
