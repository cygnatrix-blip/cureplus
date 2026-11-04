<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops anything that reaches real patients until a practice is approved. Policies and the
 * services behind these routes check again, so hiding a button is never the only protection.
 */
class EnsureOrganizationApproved
{
    public const MESSAGE = 'This opens once your account is verified.';

    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenant->organization()?->isActive()) {
            return $next($request);
        }

        // A GET goes to the verification page: going "back" could land on this same page again.
        return $request->isMethodSafe()
            ? redirect()->route('app.verification.index')->with('error', self::MESSAGE)
            : back()->with('error', self::MESSAGE);
    }
}
