<?php

namespace App\Http\Middleware;

use App\Services\Billing\FeatureGate;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies to the whole tenant route group, so a module is switched off in one place:
 * config/plans.php maps features to routes.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureGate $features, private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $this->tenant->id();

        if ($organizationId !== null && $request->routeIs(...$this->features->disabledRoutes($organizationId))) {
            return redirect()->route('app.subscription.index')->with('error', 'Your plan does not include this feature.');
        }

        return $next($request);
    }
}
