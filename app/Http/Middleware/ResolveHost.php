<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides what a hostname may reach. The platform's own host gets everything. A verified
 * custom domain gets only the public booking site. Anything else gets a plain 404, so
 * pointing a domain at this server does not expose the app. Portal logins never work on a
 * custom domain, which keeps session cookies and login forms off hosts we do not control.
 */
class ResolveHost
{
    public const ORGANIZATION_ATTRIBUTE = 'custom_domain_organization';

    private const ALLOWED_RESERVED = ['book', 'brand', 'build', 'icons', 'sw', 'sw.js', 'offline', 'privacy', 'terms', 'patient', 'pay', 'files', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($this->isPlatformHost($host)) {
            return $next($request);
        }

        $organizationId = Cache::remember('custom-domain.'.$host, 300, fn () => (int) CustomDomain::withoutTenancy()
            ->where('hostname', $host)
            ->where('status', 'active')
            ->value('organization_id'));

        $organization = $organizationId ? Organization::query()->find($organizationId) : null;

        if ($organization === null || ! $organization->isActive() || ! $this->pathAllowed($request)) {
            return response()->view('errors.404', [], 404);
        }

        $request->attributes->set(self::ORGANIZATION_ATTRIBUTE, $organization);

        return $next($request);
    }

    private function isPlatformHost(string $host): bool
    {
        $hosts = [
            strtolower((string) config('cureplus.domain')),
            strtolower((string) config('cureplus.custom_domains.connect_host')),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
        ];

        return in_array($host, $hosts, true) || (! app()->isProduction() && in_array($host, ['localhost', '127.0.0.1'], true));
    }

    private function pathAllowed(Request $request): bool
    {
        $first = explode('/', trim($request->path(), '/'))[0];

        return $first === ''
            || in_array($first, self::ALLOWED_RESERVED, true)
            || ! in_array($first, config('cureplus.reserved_slugs'), true);
    }
}
