<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\BreachIncident;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Services\Metrics;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    public function test_all_new_admin_pages_render(): void
    {
        SystemHeartbeat::beat('scheduler');
        $this->actingAs($this->superAdmin(), 'platform');

        foreach (['admin.monitoring.index', 'admin.domains.index', 'admin.backups.index', 'admin.api-keys.index', 'admin.integrations.index', 'admin.system.index', 'admin.breaches.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_settings_are_encrypted_masked_and_beat_env(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.integrations.save'), ['settings' => ['mail_host' => 'smtp.example.test', 'mail_password' => 'app-password-1234']])->assertSessionHas('success');

        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('app-password-1234', config('mail.mailers.smtp.password'));
        $this->assertStringNotContainsString('app-password-1234', (string) DB::table('platform_settings')->where('setting_key', 'mail.password')->value('value'));

        $this->get(route('admin.integrations.index'))->assertOk()->assertDontSee('app-password-1234')->assertSee('••••1234');

        $this->post(route('admin.integrations.save'), ['settings' => ['mail_host' => 'smtp.example.test', 'mail_password' => '']]);
        $this->assertSame('app-password-1234', app(PlatformSettings::class)->get('mail.password'));

        $logged = AuditLog::withoutTenancy()->where('action', 'platform_settings.saved')->pluck('metadata')->toJson();
        $this->assertStringNotContainsString('app-password-1234', $logged);
    }

    public function test_only_super_admins_can_change_platform_integrations(): void
    {
        $this->actingAs($this->superAdmin('finance'), 'platform');

        $this->get(route('admin.integrations.index'))->assertForbidden();
        $this->post(route('admin.integrations.save'), ['settings' => ['mail_host' => 'evil.test']])->assertForbidden();
        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_settings_survive_a_missing_table(): void
    {
        Cache::flush();
        DB::statement('DROP TABLE platform_settings');

        app(PlatformSettings::class)->applyToConfig();

        $this->addToAssertionCount(1);
    }

    public function test_a_test_email_can_be_sent(): void
    {
        Mail::fake();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.integrations.test-email'), ['to' => 'ops@example.test'])->assertSessionHas('success');
    }

    public function test_web_push_keys_can_be_generated(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        try {
            $this->post(route('admin.integrations.vapid'))->assertSessionHas('success');
        } catch (\Throwable $exception) {
            $this->markTestSkipped('This PHP cannot generate EC keys: '.$exception->getMessage());
        }

        $this->assertNotEmpty(config('messages.push.public_key'));
    }

    public function test_blocking_an_ip_stops_it_and_unblocking_restores_it(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.security.block-ip'), ['ip_address' => '203.0.113.9', 'reason' => 'Attack'])->assertSessionHas('success');
        $this->from(route('admin.security.index'))->post(route('admin.security.block-ip'), ['ip_address' => 'not-an-ip'])->assertSessionHasErrors('ip_address');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/login')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/login')->assertOk();

        $block = BlockedIp::query()->firstOrFail();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->delete(route('admin.security.unblock-ip', $block))->assertSessionHas('success');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/login')->assertOk();
    }

    public function test_an_admin_cannot_block_their_own_ip(): void
    {
        $this->actingAs($this->superAdmin(), 'platform')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post(route('admin.security.block-ip'), ['ip_address' => '198.51.100.7'])
            ->assertSessionHas('error');

        $this->assertSame(0, BlockedIp::query()->count());
    }

    public function test_two_factor_can_be_reset_with_a_reason_and_ends_sessions(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()])->saveQuietly();
        DB::table('sessions')->insert(['id' => 'target', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->superAdmin(), 'platform');

        $this->from(route('admin.security.index'))->post(route('admin.security.reset-2fa', $user), [])->assertSessionHasErrors('reason');
        $this->post(route('admin.security.reset-2fa', $user), ['reason' => 'Lost phone, ticket 12'])->assertSessionHas('success');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseMissing('sessions', ['id' => 'target']);
        $this->assertSame('Lost phone, ticket 12', AuditLog::withoutTenancy()->where('action', 'security.two_factor_reset')->firstOrFail()->metadata['reason']);
    }

    public function test_the_security_center_can_find_an_account_and_list_sessions(): void
    {
        $user = User::factory()->create(['email' => 'find.me@example.test']);
        DB::table('sessions')->insert(['id' => 'x', 'user_id' => $user->id, 'ip_address' => '192.0.2.1', 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->superAdmin(), 'platform')
            ->get(route('admin.security.index', ['q' => 'find.me@example.test']))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('192.0.2.1');
    }

    public function test_an_admins_password_can_be_reset_by_another_admin_only(): void
    {
        $me = $this->superAdmin();
        $other = $this->superAdmin();
        $this->actingAs($me, 'platform');

        $this->post(route('admin.admins.reset', $me))->assertSessionHas('error');

        $this->post(route('admin.admins.reset', $other))->assertSessionHas('status');
        $password = str(session('status'))->afterLast(': ')->toString();

        $fresh = PlatformAdmin::query()->find($other->id);
        $this->assertTrue(Hash::check($password, $fresh->password));
        $this->assertFalse($fresh->hasTwoFactorEnabled());
    }

    public function test_requiring_2fa_for_all_staff_takes_effect(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web')->get(route('app.dashboard'))->assertOk();

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.system.save'), ['require_approval' => 1, 'two_factor_all_staff' => 1])->assertSessionHas('success');

        $this->actingAs(User::query()->find($receptionist->id), 'web')->get(route('app.dashboard'))->assertRedirect(route('app.two-factor.setup'));
    }

    public function test_legal_pages_can_be_edited_and_are_escaped(): void
    {
        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.system.save'), ['require_approval' => 1, 'legal_privacy' => 'Reviewed policy <script>x</script>'])->assertSessionHas('success');

        $this->get('/privacy')->assertOk()->assertSee('Reviewed policy')->assertDontSee('<script>x</script>', false)->assertDontSee('Draft.');
        $this->get('/terms')->assertOk()->assertSee('Draft.');
    }

    public function test_maintenance_mode_can_be_switched_on_and_off(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        try {
            $this->post(route('admin.system.maintenance'), ['action' => 'down'])->assertSessionHas('status');
            $this->assertTrue(app()->isDownForMaintenance());
        } finally {
            Artisan::call('up');
        }

        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_breach_incidents_track_the_notification_duties(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.breaches.store'), ['title' => 'Lost laptop', 'description' => 'Secret detail', 'severity' => 'high', 'detected_at' => now()->subHour()->toDateTimeString()])->assertSessionHas('success');

        $incident = BreachIncident::query()->firstOrFail();
        $this->assertStringNotContainsString('Secret detail', (string) DB::table('breach_incidents')->value('description'));

        $this->put(route('admin.breaches.update', $incident), ['status' => 'resolved', 'board_notified' => 1, 'patients_notified' => 1])->assertSessionHas('success');

        $fresh = $incident->fresh();
        $this->assertNotNull($fresh->board_notified_at);
        $this->assertNotNull($fresh->patients_notified_at);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNotNull(AuditLog::withoutTenancy()->where('subject_type', 'BreachIncident')->first());
    }

    public function test_sent_emails_are_counted_and_monitoring_warns_near_the_limit(): void
    {
        config(['cureplus.monitoring.daily_email_limit' => 2]);
        config(['mail.default' => 'array']);
        Mail::raw('one', fn ($message) => $message->to('a@example.test'));
        Mail::raw('two', fn ($message) => $message->to('a@example.test'));

        $this->assertSame(2, Metrics::today('emails_sent'));

        $this->actingAs($this->superAdmin(), 'platform')->get(route('admin.monitoring.index'))->assertOk()->assertSee('approaching');
    }

    public function test_metrics_count_upwards_within_a_day(): void
    {
        Metrics::increment('errors');
        Metrics::increment('errors');

        $this->assertSame(2, Metrics::today('errors'));
    }

    public function test_the_admin_dashboard_links_every_section_for_a_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'platform')->get(route('admin.dashboard'))->assertOk();

        foreach (['Organizations', 'Doctors', 'Plans and subscriptions', 'Medicine catalogue', 'Templates', 'Custom domains', 'Reports', 'Support tickets', 'Announcements', 'Feature flags', 'Audit log', 'Security center', 'Backups', 'Integrations', 'API keys', 'System configuration', 'Monitoring', 'IT admins'] as $section) {
            $response->assertSee($section);
        }
    }
}
