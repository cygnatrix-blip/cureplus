<?php

namespace App\Http\Middleware;

use App\Services\Auth\LoginFlow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivation takes effect on the next click, not at the next login.
 */
class EnsureAccountActive
{
    public function __construct(private readonly LoginFlow $login) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if ($account && ! $account->isActive()) {
            return $this->login->signOut($request->is('admin', 'admin/*') ? 'platform' : 'web', $request);
        }

        return $next($request);
    }
}
