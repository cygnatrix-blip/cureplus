<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveHost;
use App\Services\Portal\PortalBrand;
use App\Services\Portal\PublicBooking;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A practice's own website. Booking is one page of it, at /book/{slug}.
 */
class ClinicSiteController extends Controller
{
    public function __construct(private readonly PublicBooking $booking) {}

    public function show(Request $request, string $slug, BookingController $bookingPage, PortalBrand $brand): View
    {
        $custom = $request->attributes->get(ResolveHost::ORGANIZATION_ATTRIBUTE);

        // On a custom domain the first path segment is a doctor, because the host already names the practice.
        if ($custom !== null && $slug !== $custom->slug) {
            return $bookingPage->show($request, $custom->slug, $slug);
        }

        $organization = $this->booking->organization($slug);
        $brand->remember($organization);

        return view('clinic.home', [
            'organization' => $organization,
            'doctors' => $this->booking->doctorCards($organization),
            'locations' => $this->booking->locations($organization),
        ]);
    }
}
