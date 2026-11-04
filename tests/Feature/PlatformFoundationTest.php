<?php

namespace Tests\Feature;

use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Session\TrackedDatabaseSessionHandler;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function portals(): array
    {
        return ['admin' => ['admin'], 'app' => ['app'], 'patient' => ['patient'], 'display' => ['display']];
    }

    #[DataProvider('portals')]
    public function test_each_portal_has_an_installable_manifest(string $portal): void
    {
        $response = $this->get("/manifests/{$portal}.webmanifest")->assertOk();
        $manifest = $response->json();

        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('Content-Type'));
        $this->assertSame("/{$portal}", $manifest['start_url']);
        $this->assertSame("/{$portal}", $manifest['scope']);
        $this->assertContains($manifest['display'], ['standalone', 'fullscreen']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['theme_color']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512', '512x512'], array_column($manifest['icons'], 'sizes'));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path($icon['src']));
            [$width] = getimagesize(public_path($icon['src']));
            $this->assertSame((int) $icon['sizes'], $width);
        }
    }

    public function test_an_unknown_manifest_is_404(): void
    {
        $this->get('/manifests/nope.webmanifest')->assertNotFound();
    }

    public function test_the_service_worker_is_versioned_and_only_caches_the_shell(): void
    {
        $response = $this->get('/sw.js')->assertOk();
        $script = $response->getContent();

        $this->assertStringContainsString('javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('__VERSION__', $script);
        $this->assertStringNotContainsString('__PRECACHE__', $script);
        $this->assertStringContainsString('/offline', $script);
        $this->assertStringContainsString("request.method !== 'GET'", $script);

        // The one line that writes to the cache must sit behind the /build and /icons check.
        $this->assertSame(1, substr_count($script, 'cache.put('));
        $this->assertLessThan(strpos($script, 'cache.put('), strpos($script, "url.pathname.startsWith('/build/')"));
    }

    public function test_stateless_endpoints_set_no_cookies(): void
    {
        foreach (['/sw.js', '/manifests/app.webmanifest'] as $path) {
            $this->assertCount(0, $this->get($path)->headers->getCookies(), $path);
        }
    }

    public function test_the_offline_page_and_portal_shells_render(): void
    {
        $this->get('/offline')->assertOk()->assertSee('You are offline');
        $this->get('/patient/login')->assertOk()->assertSee('manifests/patient.webmanifest');
        $this->get('/display')->assertOk()->assertSee('manifests/display.webmanifest');
        $this->get('/')->assertOk()->assertSee('manifests/app.webmanifest');
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }

    public function test_security_headers_are_strict(): void
    {
        $response = $this->get('/login')->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_signed_in_pages_are_not_cached(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $cacheControl = (string) $this->actingAs($owner, 'web')->get(route('app.dashboard'))->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_every_scheduled_task_runs_in_process(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            // Anything else needs proc_open, which is disabled on the shared host.
            $this->assertInstanceOf(CallbackEvent::class, $event, (string) $event->description);
        }

        $this->assertSame('queue-worker', end($events)->description, 'The queue worker must run last.');
    }

    public function test_health_reports_status_only(): void
    {
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.scheduler', false)->assertJsonPath('checks.database', true);

        SystemHeartbeat::beat('scheduler');

        $this->getJson('/health')->assertOk()->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => true, 'cache' => true, 'scheduler' => true],
        ]);

        $this->travel(config('cureplus.heartbeat.max_age_seconds') + 1)->seconds();
        $this->getJson('/health')->assertStatus(503);
    }

    public function test_sessions_record_each_guard_in_its_own_column(): void
    {
        $handler = fn () => new TrackedDatabaseSessionHandler(DB::connection(), 'sessions', 120, $this->app);
        $admin = $this->superAdmin();
        // Same numeric id on purpose: this is the collision the handler exists to prevent.
        $user = User::factory()->create(['id' => $admin->id]);

        Auth::guard('platform')->setUser($admin);
        Auth::shouldUse('platform');
        $handler()->write('admin-session', 'payload');

        $row = DB::table('sessions')->find('admin-session');
        $this->assertSame($admin->id, (int) $row->platform_admin_id);
        $this->assertNull($row->user_id);

        Auth::guard('platform')->forgetUser();
        Auth::shouldUse('web');
        Auth::guard('web')->setUser($user);
        $handler()->write('user-session', 'payload');

        $row = DB::table('sessions')->find('user-session');
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertNull($row->platform_admin_id);
    }

    public function test_no_column_silently_updates_itself(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('This is MariaDB behaviour: the first non-null TIMESTAMP gets ON UPDATE CURRENT_TIMESTAMP.');
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('extra', 'like', '%on update%')
            ->get(['table_name', 'column_name']);

        $this->assertCount(0, $columns, 'Use dateTime for: '.$columns->map(fn ($column) => json_encode($column))->implode(', '));
    }

    public function test_the_real_session_driver_is_registered(): void
    {
        $this->assertInstanceOf(TrackedDatabaseSessionHandler::class, app('session')->driver('tracked-database')->getHandler());
    }
}
