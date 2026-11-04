<?php

use App\Exceptions\OtpDeliveryException;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\BlockDuringImpersonation;
use App\Http\Middleware\BlockIps;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureContactVerified;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\ResolveHost;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Metrics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(SecurityHeaders::class)->group(base_path('routes/stateless.php'));
            Route::prefix('api/v1')->middleware('throttle:api')->group(base_path('routes/api.php'));
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/app.php'));
            Route::middleware('web')->group(base_path('routes/patient.php'));
            Route::middleware('web')->group(base_path('routes/booking.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([BlockIps::class, ResolveHost::class]);
        $middleware->web(prepend: [SecurityHeaders::class]);

        $middleware->alias([
            'account.active' => EnsureAccountActive::class,
            'api.key' => AuthenticateApiKey::class,
            'contact.verified' => EnsureContactVerified::class,
            'feature' => EnsureFeatureEnabled::class,
            'no-store' => NoStore::class,
            'not-impersonating' => BlockDuringImpersonation::class,
            'tenant' => ResolveTenant::class,
            'two-factor' => EnsureTwoFactorEnrolled::class,
        ]);

        // Listed middleware jumps ahead of unlisted middleware, so the whole chain is
        // spelled out: account active, contact verified, 2FA, tenant, then route-model
        // binding. Binding must come after the tenant, or {location} would load through
        // a scope with no tenant. TenantIsolationTest guards this ordering.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
        $middleware->prependToPriorityList(ResolveTenant::class, EnsureTwoFactorEnrolled::class);
        $middleware->prependToPriorityList(EnsureTwoFactorEnrolled::class, EnsureContactVerified::class);
        $middleware->prependToPriorityList(EnsureContactVerified::class, EnsureAccountActive::class);

        $middleware->redirectGuestsTo(
            fn (Request $request) => match (true) {
                $request->is('admin', 'admin/*') => route('admin.login'),
                $request->is('patient', 'patient/*') => route('patient.login'),
                default => route('login'),
            }
        );
        $middleware->redirectUsersTo(
            fn (Request $request) => match (true) {
                $request->is('admin', 'admin/*') => route('admin.dashboard'),
                $request->is('patient', 'patient/*') => route('patient.home'),
                default => route('app.dashboard'),
            }
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(fn (Throwable $exception) => Metrics::increment('errors'));

        $exceptions->render(fn (OtpDeliveryException $exception, Request $request) => back()->withInput()->with('error', __('We could not send the email. Please try again in a moment.')));

        // Never written to logs, even when validation fails on these fields.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code']);
    })->create();
