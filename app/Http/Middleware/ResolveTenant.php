<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use App\Models\Location;
use App\Models\Membership;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public const SESSION_ORGANIZATION = 'active_organization_id';

    public const SESSION_LOCATION = 'active_location_id';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * "tenant:optional" is for pages that work without a practice, such as My account. A
     * suspended or practice-less user can still change their password there, and everyone
     * else keeps their menu.
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $this->context->clear();
        $isOptional = $mode === 'optional';

        // The session only says which organization was picked. Access is re-checked
        // against the database on every request, so a revoked membership ends at once.
        $memberships = $request->user('web')->memberships()
            ->active()
            ->with(['organization', 'roles', 'locations'])
            ->get()
            ->filter(fn (Membership $membership) => $membership->organization !== null);

        if ($memberships->isEmpty()) {
            return $isOptional ? $next($request) : redirect()->route('app.no-organization');
        }

        $membership = $memberships->firstWhere('organization_id', $request->session()->get(self::SESSION_ORGANIZATION));

        if ($membership === null && $memberships->count() > 1) {
            return $isOptional ? $next($request) : redirect()->route('app.organizations');
        }

        $membership ??= $memberships->first();
        $organization = $membership->organization;
        $request->session()->put(self::SESSION_ORGANIZATION, $organization->id);

        if ($organization->status === OrganizationStatus::Suspended || $organization->accessHasEnded()) {
            return $isOptional ? $next($request) : redirect()->route('app.organization.status');
        }

        $this->context->set($organization, $membership);
        $this->context->setLocation($this->resolveLocation($request, $membership));

        return $next($request);
    }

    /**
     * Tests share one application across requests, and so would a long-running
     * server later: the tenant must not outlive its request.
     */
    public function terminate(): void
    {
        $this->context->clear();
    }

    private function resolveLocation(Request $request, Membership $membership): ?Location
    {
        $locations = Location::query()->active()->orderBy('name')->get()
            ->filter(fn (Location $location) => $membership->canAccessLocation($location->id));

        $location = $locations->firstWhere('id', $request->session()->get(self::SESSION_LOCATION)) ?? $locations->first();

        $request->session()->put(self::SESSION_LOCATION, $location?->id);

        return $location;
    }
}
