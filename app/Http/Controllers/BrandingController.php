<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public by design: these are what a patient's phone needs to install "City Clinic".
 */
class BrandingController extends Controller
{
    public function manifest(string $slug): JsonResponse
    {
        $organization = $this->activeOrganization($slug);
        $theme = $organization->brand_color ?: config('cureplus.portals.patient.theme_color');
        $hasIcon = $organization->icon_192_path !== null;

        return response()->json([
            'name' => $organization->name,
            'short_name' => mb_substr($organization->name, 0, 12),
            'id' => '/'.$organization->slug,
            'start_url' => '/'.$organization->slug,
            // Whole-site scope, so signing in on /patient stays inside the installed app.
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => $theme,
            'icons' => $hasIcon
                ? [
                    ['src' => route('brand.icon', [$organization->slug, 192]), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => route('brand.icon', [$organization->slug, 512]), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ]
                : [
                    ['src' => '/icons/patient-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/icons/patient-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/icons/patient-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function icon(string $slug, int $size): Response
    {
        $organization = $this->activeOrganization($slug);
        $path = $size === 512 ? $organization->icon_512_path : $organization->icon_192_path;
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($path !== null && in_array($size, [192, 512], true) && $disk->exists($path), 404);

        return response((string) $disk->get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function cover(string $slug): Response
    {
        $organization = $this->activeOrganization($slug);
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($organization->cover_image_path !== null && $disk->exists($organization->cover_image_path), 404);

        return response((string) $disk->get($organization->cover_image_path), 200, ['Content-Type' => str_ends_with($organization->cover_image_path, '.png') ? 'image/png' : 'image/jpeg', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function activeOrganization(string $slug): Organization
    {
        $organization = Organization::query()->where('slug', $slug)->firstOrFail();
        abort_unless($organization->isActive(), 404);

        return $organization;
    }
}
