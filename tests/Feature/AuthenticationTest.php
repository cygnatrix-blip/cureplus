<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\Auth\OtpService;
use App\Services\UserSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_staff_can_sign_in_with_email_or_mobile_in_any_format(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['receptionist'], User::factory()->create(['mobile' => '+919812345678', 'email' => 'mita@example.test']), $location);

        foreach (['MITA@example.test', '9812345678', '+91 98123 45678', '09812345678'] as $identifier) {
            $this->post('/login', ['identifier' => $identifier, 'password' => 'Password123'])->assertRedirect(route('app.dashboard'));
            $this->assertAuthenticatedAs($user, 'web');
            $this->post(route('app.logout'));
            $this->assertGuest('web');
        }
    }

    public function test_wrong_and_unknown_credentials_get_the_same_answer(): void
    {
        $user = User::factory()->create(['email' => 'real@example.test']);

        $message = ['identifier' => 'These details do not match our records.'];

        $this->from('/login')->post('/login', ['identifier' => 'real@example.test', 'password' => 'nope'])->assertSessionHasErrors($message);
        $this->from('/login')->post('/login', ['identifier' => 'ghost@example.test', 'password' => 'nope'])->assertSessionHasErrors($message);
        $this->assertGuest('web');
        $this->assertSame(2, SecurityEvent::query()->where('type', 'login_failed')->count());
        $this->assertStringNotContainsString('real@', (string) SecurityEvent::query()->first()->identifier_masked);
        $this->assertSame(1, $user->fresh()->failed_login_attempts);
    }

    public function test_an_account_locks_after_repeated_failures(): void
    {
        $user = User::factory()->create(['email' => 'real@example.test']);

        for ($i = 0; $i < config('cureplus.security.max_login_attempts'); $i++) {
            $this->post('/login', ['identifier' => 'real@example.test', 'password' => 'nope']);
        }

        $this->assertTrue($user->fresh()->isLocked());

        $this->post('/login', ['identifier' => 'real@example.test', 'password' => 'Password123'])->assertSessionHasErrors('identifier');
        $this->assertGuest('web');
        $this->assertSame(1, SecurityEvent::query()->where('type', 'login_locked')->count());

        $this->travel(config('cureplus.security.lockout_minutes') + 1)->minutes();
        $this->post('/login', ['identifier' => 'real@example.test', 'password' => 'Password123']);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.test']);
        $user->forceFill(['status' => 'inactive'])->saveQuietly();

        $this->post('/login', ['identifier' => 'gone@example.test', 'password' => 'Password123'])->assertSessionHasErrors('identifier');
        $this->assertGuest('web');
    }

    public function test_a_deactivated_account_is_signed_out_on_its_next_request(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk();

        $owner->forceFill(['status' => 'inactive'])->saveQuietly();

        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_an_owner_without_2fa_is_sent_to_setup_and_cannot_use_the_portal(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $owner->forceFill(['two_factor_confirmed_at' => null, 'two_factor_method' => null])->saveQuietly();

        $this->actingAs($owner, 'web');

        $this->get(route('app.dashboard'))->assertRedirect(route('app.two-factor.setup'));
        $this->get(route('app.locations.index'))->assertRedirect(route('app.two-factor.setup'));
        $this->get(route('app.two-factor.setup'))->assertOk();
    }

    public function test_totp_setup_then_login_requires_the_code(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $owner->forceFill(['two_factor_confirmed_at' => null, 'two_factor_method' => null])->saveQuietly();
        $google2fa = app(Google2FA::class);

        $this->actingAs($owner, 'web')->get(route('app.two-factor.setup'))->assertOk();
        $secret = session('two_factor.setup_secret');

        $this->post(route('app.two-factor.totp'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($owner->fresh()->hasTwoFactorEnabled());

        $this->post(route('app.two-factor.totp'), ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertRedirect(route('app.two-factor.setup'))
            ->assertSessionHas('two_factor.recovery_codes');

        $owner = $owner->fresh();
        $this->assertTrue($owner->hasTwoFactorEnabled());
        // Stored encrypted, so the raw column must not contain the secret.
        $this->assertStringNotContainsString($secret, (string) DB::table('users')->where('id', $owner->id)->value('two_factor_secret'));

        $this->post(route('app.logout'));

        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest('web');
        $this->get(route('app.dashboard'))->assertRedirect(route('login'));

        $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest('web');

        // The code used during setup is still in its time window, but already spent.
        $this->from(route('two-factor.challenge'))->post(route('two-factor.challenge'), ['code' => $google2fa->getCurrentOtp($secret)])->assertSessionHasErrors('code');
        $this->assertGuest('web');

        // Google2FA reads the real clock, so a fresh code comes from the next time step.
        $code = $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
        $this->from(route('two-factor.challenge'))->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($owner, 'web');

        // The same code cannot be used a second time.
        $this->post(route('app.logout'));
        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123']);
        $this->from(route('two-factor.challenge'))->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_a_recovery_code_works_exactly_once(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $owner->forceFill(['two_factor_method' => 'totp', 'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32), 'two_factor_recovery_codes' => ['abcde-fghij']])->saveQuietly();

        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123']);
        $this->post(route('two-factor.challenge'), ['code' => 'ABCDE-FGHIJ'])->assertRedirect(route('app.dashboard'));
        $this->post(route('app.logout'));

        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123']);
        $this->post(route('two-factor.challenge'), ['code' => 'abcde-fghij'])->assertSessionHasErrors('code');
    }

    public function test_email_2fa_sends_a_code_at_login_and_verifies_it(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123'])->assertRedirect(route('two-factor.challenge'));

        $this->assertSame(1, $this->emailedCodeCount());
        $this->post(route('two-factor.challenge'), ['code' => $this->lastEmailedCode()])->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($owner, 'web');
    }

    public function test_2fa_failures_count_toward_lockout_and_a_stale_challenge_expires(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123']);

        for ($i = 0; $i < config('cureplus.security.max_login_attempts'); $i++) {
            $this->post(route('two-factor.challenge'), ['code' => '000000']);
        }

        $this->assertTrue($owner->fresh()->isLocked());
        $this->post(route('two-factor.challenge'), ['code' => $this->lastEmailedCode()])->assertRedirect(route('login'));
        $this->assertGuest('web');

        $this->travel(config('cureplus.security.lockout_minutes') + 1)->minutes();
        $this->post('/login', ['identifier' => $owner->email, 'password' => 'Password123']);
        $this->travel(config('cureplus.security.two_factor_pending_minutes') + 1)->minutes();
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_staff_can_sign_in_with_an_email_code(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['receptionist'], location: $location);

        $this->post('/login/otp', ['email' => $user->email])->assertRedirect(route('login.otp.verify'));
        $this->post('/login/otp/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/login/otp/verify', ['code' => $this->lastEmailedCode()])->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_owners_and_doctors_cannot_bypass_2fa_with_an_email_code(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->post('/login/otp', ['email' => $owner->email])->assertRedirect(route('login.otp.verify'));

        $this->assertSame(0, $this->emailedCodeCount());

        // Even with a valid code in hand, the login is refused.
        $code = app(OtpService::class)->issue(OtpPurpose::Login, $owner->email);
        $this->post('/login/otp/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_the_otp_form_does_not_reveal_who_has_an_account(): void
    {
        [$organization] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['nurse']);

        $known = $this->post('/login/otp', ['email' => $user->email]);
        $unknown = $this->post('/login/otp', ['email' => 'nobody@example.test']);

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        $this->assertSame(1, $this->emailedCodeCount());
    }

    public function test_email_verification_link_must_be_signed_and_for_this_account(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $link = fn (User $for) => URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $for->id, 'hash' => sha1((string) $for->email)]);

        $this->actingAs($user, 'web');

        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1((string) $user->email)]))->assertForbidden();
        $this->get($link($other))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);

        $this->get($link($user))->assertRedirect(route('app.dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_unverified_user_is_held_at_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'web')->get(route('app.dashboard'))->assertRedirect(route('app.verification.notice'));
    }

    public function test_password_reset_changes_the_password_and_ends_old_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->post(route('password.email'), ['email' => 'reset@example.test'])->assertSessionHas('status');
        $this->post(route('password.email'), ['email' => 'ghost@example.test'])->assertSessionHas('status');
        Notification::assertSentTo($user, QueuedResetPassword::class);

        $token = Password::createToken($user);
        $this->post(route('password.update'), ['token' => 'wrong', 'email' => 'reset@example.test', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), ['token' => $token, 'email' => 'reset@example.test', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->post('/login', ['identifier' => 'reset@example.test', 'password' => 'NewPassword123']);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_changing_a_password_needs_the_current_one_and_signs_out_other_devices(): void
    {
        [, $owner] = $this->organizationWithOwner();
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($owner, 'web');

        $this->from(route('app.account'))->put(route('app.account.password'), ['current_password' => 'wrong', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('sessions', ['id' => 'other-device']);

        $this->from(route('app.account'))->put(route('app.account.password'), ['current_password' => 'Password123', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_a_user_can_revoke_their_own_session_but_not_someone_elses(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $stranger = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'mine', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'theirs', 'user_id' => $stranger->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($owner, 'web');

        $this->delete(route('app.account.sessions.revoke', hash('sha256', 'theirs')));
        $this->assertDatabaseHas('sessions', ['id' => 'theirs']);

        $this->delete(route('app.account.sessions.revoke', hash('sha256', 'mine')));
        $this->assertDatabaseMissing('sessions', ['id' => 'mine']);
    }

    public function test_the_account_page_never_exposes_raw_session_ids(): void
    {
        [, $owner] = $this->organizationWithOwner();
        DB::table('sessions')->insert(['id' => 'raw-session-id-value', 'user_id' => $owner->id, 'user_agent' => 'Phone', 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($owner, 'web')->get(route('app.account'))->assertOk()->assertSee('Phone')->assertDontSee('raw-session-id-value');
    }

    public function test_admin_login_requires_2fa_setup_then_challenge(): void
    {
        $admin = PlatformAdmin::factory()->create(['email' => 'ops@example.test']);

        $this->post('/admin/login', ['identifier' => 'ops@example.test', 'password' => 'Password123'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.setup'));

        $admin->forceFill(['two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()])->saveQuietly();
        $this->post('/admin/logout');

        $this->post('/admin/login', ['identifier' => 'ops@example.test', 'password' => 'Password123'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest('platform');

        $this->post(route('two-factor.challenge'), ['code' => $this->lastEmailedCode()])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_a_code_that_cannot_be_emailed_gives_a_clear_message_not_an_error_page(): void
    {
        config(['mail.mailers.array.transport' => 'nonsense', 'mail.default' => 'array']);
        app('mail.manager')->purge('array');
        $user = User::factory()->create();

        $this->from('/patient/login')->post('/patient/login', ['email' => $user->email])
            ->assertRedirect('/patient/login')
            ->assertSessionHas('error', 'We could not send the email. Please try again in a moment.');
    }

    public function test_a_clinic_user_cannot_log_in_at_the_admin_door(): void
    {
        User::factory()->create(['email' => 'clinic@example.test']);

        $this->post('/admin/login', ['identifier' => 'clinic@example.test', 'password' => 'Password123'])->assertSessionHasErrors('identifier');
        $this->assertGuest('platform');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['identifier' => 'ghost@example.test', 'password' => 'nope']);
        }

        $this->post('/login', ['identifier' => 'ghost@example.test', 'password' => 'nope'])->assertStatus(429);
    }

    public function test_logins_are_audited_without_the_password(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['nurse'], location: $location);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123']);

        $log = AuditLog::withoutTenancy()->where('action', 'auth.login')->firstOrFail();
        $this->assertSame($user->id, $log->actor_id);
        $this->assertStringNotContainsString('Password123', json_encode($log->toArray()));
    }

    public function test_user_sessions_service_ignores_admin_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'admin-session', 'platform_admin_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->assertCount(0, app(UserSessions::class)->forUser($user));
        $this->assertSame(0, app(UserSessions::class)->revokeAll($user));
    }

    public function test_a_pending_organization_can_sign_in_and_is_told_it_is_under_verification(): void
    {
        $organization = Organization::factory()->pending()->create();
        $owner = $this->addMember($organization, ['owner']);

        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk()->assertSee('under verification');
        $this->get(route('app.organization.status'))->assertRedirect(route('app.dashboard'));
    }
}
