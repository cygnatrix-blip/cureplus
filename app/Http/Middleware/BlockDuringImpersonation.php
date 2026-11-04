<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Support staff may see what the owner sees, but must never be able to take the
 * account over by changing its password or 2FA.
 */
class BlockDuringImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->session()->has('impersonation.admin_id'), 403, 'Not available while impersonating.');

        return $next($request);
    }
}
