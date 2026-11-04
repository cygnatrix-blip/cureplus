<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subscriptions are switched off for now: charges are agreed with each practice directly.
 * While off, these pages do not exist, and nothing is limited, expired or suspended.
 */
class EnsureSubscriptionsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('plans.enabled'), 404);

        return $next($request);
    }
}
