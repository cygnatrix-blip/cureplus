<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(string $portal): JsonResponse
    {
        $config = config("cureplus.portals.{$portal}");
        abort_if($config === null, 404);

        $icon = fn (string $file, string $size, string $purpose = 'any') => [
            'src' => "/icons/{$config['icon']}-{$file}.png",
            'sizes' => $size,
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()->json([
            'name' => $config['name'],
            'short_name' => $config['short_name'],
            'id' => $config['start_url'],
            'start_url' => $config['start_url'],
            'scope' => $config['scope'],
            'display' => $config['display'] ?? 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => $config['theme_color'],
            'icons' => [
                $icon('192', '192x192'),
                $icon('512', '512x512'),
                $icon('maskable-512', '512x512', 'maskable'),
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Served through Laravel so the cache version follows the build. One deploy
     * changes the worker, which is what triggers "Update available".
     */
    public function serviceWorker(): Response
    {
        $manifestPath = public_path('build/manifest.json');
        $manifest = is_file($manifestPath) ? (string) file_get_contents($manifestPath) : '{}';

        $assets = collect((array) json_decode($manifest, true))
            ->map(fn (array $entry) => '/build/'.$entry['file'])
            ->values()
            ->push('/offline')
            ->all();

        $script = str_replace(
            ['__VERSION__', '__PRECACHE__'],
            [substr(hash('sha256', $manifest), 0, 12), json_encode($assets, JSON_UNESCAPED_SLASHES)],
            (string) file_get_contents(resource_path('pwa/sw.js')),
        );

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
