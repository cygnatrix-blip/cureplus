<?php

namespace App\Services;

use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gets a locked-out admin back in: a one-time temporary password, the lock lifted, two-factor
 * cleared so it is set up again, and every session ended. Used by another admin from the
 * Admins page, or from the server's command line when no other admin can sign in.
 */
class PlatformAdminRecovery
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, string>  $metadata
     */
    public function reset(PlatformAdmin $admin, array $metadata = []): string
    {
        $password = Str::password(20);

        $admin->forceFill([
            'password' => $password,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        DB::table('sessions')->where('platform_admin_id', $admin->id)->delete();
        $this->audit->record('platform_admins.password_reset', $admin, $metadata);

        return $password;
    }
}
