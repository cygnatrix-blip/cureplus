<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRecoveryTest extends TestCase
{
    private function lockedOutAdmin(): PlatformAdmin
    {
        $admin = $this->superAdmin();
        $admin->forceFill([
            'email' => 'boss@example.test',
            'failed_login_attempts' => 9,
            'locked_until' => now()->addHour(),
            'two_factor_method' => 'totp',
            'two_factor_secret' => 'secret',
            'two_factor_confirmed_at' => now(),
        ])->save();
        DB::table('sessions')->insert(['id' => 'old-session', 'platform_admin_id' => $admin->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        return $admin;
    }

    public function test_the_command_gives_a_working_temporary_password_and_clears_the_lock_and_two_factor(): void
    {
        $admin = $this->lockedOutAdmin();

        $this->assertSame(0, Artisan::call('cureplus:reset-admin', ['email' => 'BOSS@example.test', '--force' => true]));

        $admin->refresh();
        $password = trim(collect(explode("\n", Artisan::output()))->filter()->values()->get(1, ''));
        $this->assertSame(20, mb_strlen($password));
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertSame(0, (int) $admin->failed_login_attempts);
        $this->assertNull($admin->locked_until);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNull($admin->two_factor_secret);
        $this->assertSame(0, DB::table('sessions')->where('platform_admin_id', $admin->id)->count());
        $this->assertSame(['via' => 'command line'], AuditLog::withoutTenancy()->where('action', 'platform_admins.password_reset')->firstOrFail()->metadata);
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $admin = $this->lockedOutAdmin();
        $before = $admin->password;

        $this->artisan('cureplus:reset-admin', ['email' => 'boss@example.test'])
            ->expectsConfirmation("Reset {$admin->name}? Their password and two-factor sign-in will be replaced, and they will be signed out everywhere.", 'no')
            ->expectsOutput('Nothing was changed.')
            ->assertSuccessful();

        $this->assertSame($before, $admin->refresh()->password);
        $this->assertNotNull($admin->two_factor_confirmed_at);
    }

    public function test_an_unknown_email_fails_without_saying_more(): void
    {
        $this->artisan('cureplus:reset-admin', ['email' => 'nobody@example.test', '--force' => true])
            ->expectsOutput('No admin has that email.')
            ->assertFailed();
    }

    public function test_a_deactivated_admin_is_warned_they_still_cannot_sign_in(): void
    {
        $admin = $this->lockedOutAdmin();
        $admin->forceFill(['status' => 'inactive'])->save();

        $this->artisan('cureplus:reset-admin', ['email' => 'boss@example.test', '--force' => true])
            ->expectsOutputToContain('This admin is deactivated')
            ->assertSuccessful();
    }
}
