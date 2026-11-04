<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveHost;
use App\Services\PlatformSettings;
use App\Services\Portal\PortalBrand;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * On a custom domain the home page is that practice's own website.
     */
    public function __invoke(Request $request, ClinicSiteController $site, BookingController $booking, PortalBrand $brand): View
    {
        $organization = $request->attributes->get(ResolveHost::ORGANIZATION_ATTRIBUTE);

        return $organization ? $site->show($request, $organization->slug, $booking, $brand) : view('public.landing');
    }

    public function privacy(PlatformSettings $settings): View
    {
        return view('public.privacy', ['text' => $settings->get('legal.privacy')]);
    }

    public function terms(PlatformSettings $settings): View
    {
        return view('public.terms', ['text' => $settings->get('legal.terms')]);
    }
}
