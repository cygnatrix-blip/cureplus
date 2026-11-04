<?php

namespace App\Models;

use App\Models\Concerns\HasLoginProtection;
use App\Models\Concerns\HasTwoFactor;
use Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $locked_until
 * @property Carbon|null $two_factor_confirmed_at
 * @property list<string>|null $two_factor_recovery_codes
 *
 * Platform staff. A separate table and guard from clinic users, so a stolen clinic
 * account or a bug in tenant code can never turn into platform access.
 */
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory, HasLoginProtection, HasTwoFactor, Notifiable, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function requiresTwoFactor(): bool
    {
        return true;
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = config("rbac.platform_roles.{$this->role}.abilities", []);

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function roleName(): string
    {
        return config("rbac.platform_roles.{$this->role}.name", $this->role);
    }
}
