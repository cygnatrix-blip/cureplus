<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class BlockIps
{
    public const CACHE_KEY = 'security.blocked-ips';

    public function handle(Request $request, Closure $next): Response
    {
        $blocked = Cache::remember(self::CACHE_KEY, 300, fn () => BlockedIp::query()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('ip_address')
            ->all());

        abort_if(in_array($request->ip(), $blocked, true), 403);

        return $next($request);
    }
}
