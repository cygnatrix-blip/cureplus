<?php

namespace App\Tenancy\Jobs;

use App\Models\Organization;
use App\Tenancy\TenantContext;
use Closure;

/**
 * Job middleware. Jobs carry the organization id, never a live context, and
 * each one runs with its own tenant so nothing carries over between jobs.
 */
class RestoresTenant
{
    public function __construct(private readonly int $organizationId) {}

    public function handle(object $job, Closure $next): void
    {
        $context = app(TenantContext::class);
        $context->clear();

        try {
            $context->set(Organization::query()->findOrFail($this->organizationId));
            $next($job);
        } finally {
            $context->clear();
        }
    }
}
