<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $this->tenant->clear();
        $token = (string) $request->bearerToken();
        $key = $token === '' ? null : ApiKey::withoutTenancy()->where('key_hash', hash('sha256', $token))->first();

        if ($key === null || ! $key->isUsable()) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if (! in_array($scope, $key->scopes, true)) {
            return response()->json(['message' => 'This key does not have that permission.'], 403);
        }

        if ($key->organization_id !== null) {
            $organization = Organization::query()->find($key->organization_id);

            if (! $organization?->isActive()) {
                return response()->json(['message' => 'Invalid API key.'], 401);
            }

            // Everything this key reads goes through the normal tenant scope.
            $this->tenant->set($organization);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_key', $key);

        return $next($request);
    }

    public function terminate(): void
    {
        $this->tenant->clear();
    }
}
