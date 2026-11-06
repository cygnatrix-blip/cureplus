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

    /**
     * Activation fetches this over HTTPS on the new domain. Only a registered domain gets an
     * answer, and the answer is a token only this app can produce, so it proves the domain
     * reaches us. A plain health check would pass for any server.
     */
    public const PROBE_PATH = '.well-known/cureplus-domain';

    private const ALLOWED_RESERVED = ['book', 'brand', 'build', 'icons', 'sw', 'sw.js', 'offline', 'privacy', 'terms', 'patient', 'pay', 'files', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = self::normalize($request->getHost());

        if ($this->isPlatformHost($host)) {
            return $next($request);
        }

        if (trim($request->path(), '/') === self::PROBE_PATH) {
            return CustomDomain::withoutTenancy()->where('hostname', $host)->exists()
                ? response(self::probeToken($host), 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store'])
                : response()->view('errors.404', [], 404);
        }

        $organizationId = $this->activeOrganizationId($host);

        // www.example.com goes to example.com, unless the clinic registered the www form itself.
        if ($organizationId === 0 && str_starts_with($host, 'www.') && $this->activeOrganizationId(substr($host, 4)) !== 0) {
            return redirect()->away('https://'.substr($host, 4).$request->getRequestUri(), 301);
        }

        $organization = $organizationId ? Organization::query()->find($organizationId) : null;

        if ($organization === null || ! $organization->isActive() || ! $this->pathAllowed($request)) {
            return response()->view('errors.404', [], 404);
        }

        $request->attributes->set(self::ORGANIZATION_ATTRIBUTE, $organization);

        return $next($request);
    }

    /**
     * The form hostnames are stored in: lowercase, no trailing dot. The request already drops the port.
     */
    public static function normalize(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }

    public static function probeToken(string $host): string
    {
        return hash_hmac('sha256', 'custom-domain:'.$host, (string) config('app.key'));
    }

    private function activeOrganizationId(string $host): int
    {
        return Cache::remember('custom-domain.'.$host, 300, fn () => (int) CustomDomain::withoutTenancy()
            ->where('hostname', $host)
            ->where('status', 'active')
            ->value('organization_id'));
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
