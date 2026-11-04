<?php

namespace App\Services\Portal;

use App\Http\Middleware\ResolveHost;
use App\Models\Organization;

/**
 * Which clinic's look the patient pages should wear. A custom domain decides it outright.
 * Otherwise it is the clinic whose booking page the patient came through last, so someone
 * who books with City Clinic stays in City Clinic's colours when they reach their dashboard.
 */
class PortalBrand
{
    private const SESSION_KEY = 'portal_brand_organization_id';

    public function remember(Organization $organization): void
    {
        session()->put(self::SESSION_KEY, $organization->id);
    }

    public function current(): ?Organization
    {
        $custom = request()->attributes->get(ResolveHost::ORGANIZATION_ATTRIBUTE);

        if ($custom instanceof Organization) {
            return $custom;
        }

        $id = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;
        $organization = $id ? Organization::query()->find($id) : null;

        return $organization?->isActive() ? $organization : null;
    }
}
