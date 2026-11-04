<?php

namespace App\Providers;

use App\Contracts\CaptchaVerifier;
use App\Contracts\DnsResolver;
use App\Contracts\PushSender;
use App\Contracts\SmsProvider;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\AnnouncementFeed;
use App\Services\Billing\FeatureGate;
use App\Services\Captcha\NullCaptcha;
use App\Services\Captcha\TurnstileCaptcha;
use App\Services\Domains\PhpDnsResolver;
use App\Services\Messaging\WebPushSender;
use App\Services\Metrics;
use App\Services\PlatformSettings;
use App\Services\Rbac\PermissionResolver;
use App\Services\Sms\HttpSmsProvider;
use App\Services\Sms\LogSmsProvider;
use App\Session\TrackedDatabaseSessionHandler;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, so queue workers get a fresh context for every job.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(FeatureGate::class);

        $this->app->bind(SmsProvider::class, fn () => match (config('cureplus.sms.driver')) {
            'http' => new HttpSmsProvider,
            default => new LogSmsProvider,
        });

        $this->app->bind(PushSender::class, WebPushSender::class);
        $this->app->bind(DnsResolver::class, PhpDnsResolver::class);
        $this->app->singleton(PlatformSettings::class);

        $this->app->bind(CaptchaVerifier::class, fn () => match (config('cureplus.captcha.driver')) {
            'turnstile' => new TurnstileCaptcha,
            default => new NullCaptcha,
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        if ($proxies = config('cureplus.security.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(10)->letters()->numbers());

        Session::extend('tracked-database', fn ($app) => new TrackedDatabaseSessionHandler(
            $app['db']->connection(config('session.connection')),
            (string) config('session.table'),
            (int) config('session.lifetime'),
            $app,
        ));

        app(PlatformSettings::class)->applyToConfig();

        Event::listen(MessageSent::class, fn () => Metrics::increment('emails_sent'));

        $this->registerAuthorization();
        $this->registerRateLimits();
        $this->registerViewData();
    }

    /**
     * Every permission check in the app passes through here, for both guards. A
     * clinic user can never hold a platform ability, and an admin can never hold a
     * tenant permission (impersonation signs in as the owner instead).
     */
    private function registerAuthorization(): void
    {
        Gate::before(function ($actor, string $ability): ?bool {
            if (str_starts_with($ability, 'platform.')) {
                return $actor instanceof PlatformAdmin && $actor->hasAbility($ability);
            }

            if (! array_key_exists($ability, config('rbac.permissions'))) {
                return null; // Not a permission key: let the model policy decide.
            }

            $membership = app(TenantContext::class)->membership();

            return $actor instanceof User
                && $membership instanceof Membership
                && $membership->user_id === $actor->id
                && app(PermissionResolver::class)->allows($membership, $ability);
        });
    }

    private function registerRateLimits(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login|'.$request->ip().'|'.mb_strtolower((string) $request->input('identifier'))),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(3)->by('otp|'.$request->ip().'|'.$request->input('email', $request->input('mobile'))),
            Limit::perHour(20)->by('otp-ip|'.$request->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify|'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register|'.$request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by('api|'.hash('sha256', (string) $request->bearerToken()).'|'.$request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(20)->by('booking|'.$request->ip()));
    }

    private function registerViewData(): void
    {
        View::composer(['app.*', 'components.layouts.app'], fn ($view) => $view->with('tenant', app(TenantContext::class)));

        View::composer('components.layouts.app', function ($view): void {
            $user = Auth::guard('web')->user();
            $tenant = app(TenantContext::class);

            $view->with('announcements', $user && $tenant->has() ? app(AnnouncementFeed::class)->for((int) $tenant->id(), $user->id) : collect());
            $view->with('disabledRoutes', $tenant->has() ? app(FeatureGate::class)->disabledRoutes((int) $tenant->id()) : []);
            $view->with('otherMembershipCount', $user ? max(0, $user->memberships()->where('status', 'active')->count() - 1) : 0);
        });
    }
}
