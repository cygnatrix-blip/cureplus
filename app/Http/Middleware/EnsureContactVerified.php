<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everyone signs in with an email they have proved. Kept separate from Laravel's
 * `verified` middleware because it also redirects to this app's own notice page.
 */
class EnsureContactVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedContact()) {
            return redirect()->route('app.verification.notice');
        }

        return $next($request);
    }
}
