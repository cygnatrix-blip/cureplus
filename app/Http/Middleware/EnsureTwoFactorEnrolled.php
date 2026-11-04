<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        // The impersonating admin already passed their own 2FA.
        if ($request->session()->has('impersonation.admin_id') && ! $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $account = $request->user();

        if ($account && $account->requiresTwoFactor() && ! $account->hasTwoFactorEnabled()) {
            return redirect()->route($request->is('admin', 'admin/*') ? 'admin.two-factor.setup' : 'app.two-factor.setup');
        }

        return $next($request);
    }
}
