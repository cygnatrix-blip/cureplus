<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\BrandingService;
use App\Support\BrandTheme;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * How a clinic's patient pages look. The layout and flow stay the same for everyone;
 * colours, logo, photo and wording are the clinic's own.
 */
class DesignController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function edit(): View
    {
        return view('app.settings.design', [
            'organization' => $this->tenant->organization(),
            'presets' => BrandTheme::PRESETS,
            'corners' => array_keys(BrandTheme::CORNERS),
            'canReadJpeg' => function_exists('imagecreatefromjpeg'),
        ]);
    }

    /**
     * The practice's own images for its own pages. The public route only serves approved
     * practices, so without this a practice could not see the logo it had just uploaded.
     */
    public function image(string $kind): Response
    {
        $organization = $this->tenant->organization();
        $path = match (true) {
            $kind === 'logo' => $organization->icon_192_path,
            $kind === 'cover' => $organization->cover_image_path,
            default => $organization->branding['gallery'][(int) substr($kind, 8)] ?? null,
        };
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($path !== null && $disk->exists($path), 404);

        return response((string) $disk->get($path), 200, ['Content-Type' => str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg']);
    }

    public function update(Request $request, BrandingService $branding, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            // Strict patterns: these values are written into a style block on public pages.
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'corners' => ['required', Rule::in(array_keys(BrandTheme::CORNERS))],
            'tagline' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:800'],
            'highlights' => ['nullable', 'string', 'max:300'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'show_fees' => ['sometimes', 'boolean'],
            'established_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'services' => ['nullable', 'array', 'max:6'],
            'services.*.title' => ['nullable', 'string', 'max:60'],
            'services.*.text' => ['nullable', 'string', 'max:160'],
            'faqs' => ['nullable', 'array', 'max:5'],
            'faqs.*.question' => ['nullable', 'string', 'max:150'],
            'faqs.*.answer' => ['nullable', 'string', 'max:400'],
            'gallery' => ['nullable', 'array', 'max:6'],
            'gallery.*' => ['image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['integer'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'remove_cover' => ['sometimes', 'boolean'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], [
            'brand_color.regex' => 'Use a colour like #088575.',
            'accent_color.regex' => 'Use a colour like #f59e0b.',
            'logo.uploaded' => 'This file is too large for the server to accept. Please choose a smaller one.',
            'cover.uploaded' => 'This file is too large for the server to accept. Please choose a smaller one.',
            'logo.mimes' => 'Please use a PNG or JPG image.',
            'cover.mimes' => 'Please use a PNG or JPG image.',
        ]);

        $organization = $this->tenant->organization();

        $organization->forceFill([
            'brand_color' => $data['brand_color'] ?? null,
            'branding' => [
                'accent_color' => $data['accent_color'] ?? null,
                'corners' => $data['corners'],
                'tagline' => $data['tagline'] ?? null,
                'about' => $data['about'] ?? null,
                'highlights' => $this->highlights($data['highlights'] ?? null),
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'show_fees' => (bool) ($data['show_fees'] ?? false),
                'established_year' => isset($data['established_year']) ? (int) $data['established_year'] : null,
                'services' => $this->filled($data['services'] ?? [], 'title'),
                'faqs' => $this->filled($data['faqs'] ?? [], 'question', 'answer'),
                'gallery' => $organization->branding['gallery'] ?? [],
            ],
        ])->save();

        if ($request->boolean('remove_logo')) {
            $branding->removeLogo($organization);
        } elseif ($request->hasFile('logo')) {
            $branding->saveLogo($organization, $request->file('logo'));
        }

        if ($request->boolean('remove_cover')) {
            $branding->removeCover($organization);
        } elseif ($request->hasFile('cover')) {
            $branding->saveCover($organization, $request->file('cover'));
        }

        $this->updateGallery($request, $organization, $branding);

        $audit->record('organizations.design_changed', $organization);

        return back()->with('success', 'Your design is saved. Patients see it right away.');
    }

    /**
     * @return list<string>
     */
    private function highlights(?string $text): array
    {
        $lines = array_map('trim', preg_split('/\R/', (string) $text) ?: []);

        return array_slice(array_values(array_filter($lines, fn (string $line) => $line !== '')), 0, 4);
    }

    /**
     * Rows the practice left blank are dropped, so an empty section hides itself on the site.
     *
     * @param  array<int|string, array<string, string|null>>  $rows
     * @return list<array<string, string>>
     */
    private function filled(array $rows, string ...$required): array
    {
        $kept = array_filter($rows, fn (array $row) => array_reduce($required, fn (bool $carry, string $key) => $carry && filled($row[$key] ?? null), true));

        return array_values(array_map(fn (array $row) => array_map(fn (?string $value) => (string) $value, $row), $kept));
    }

    private function updateGallery(Request $request, Organization $organization, BrandingService $branding): void
    {
        $paths = $organization->branding['gallery'] ?? [];
        $disk = Storage::disk(config('filesystems.default'));

        foreach (array_map('intval', (array) $request->input('remove_gallery', [])) as $index) {
            if (isset($paths[$index])) {
                $disk->delete($paths[$index]);
                unset($paths[$index]);
            }
        }

        foreach ((array) $request->file('gallery', []) as $file) {
            if (count($paths) < 6) {
                $paths[] = $branding->saveGalleryPhoto($organization, $file);
            }
        }

        $organization->forceFill(['branding' => ['gallery' => array_values($paths)] + ($organization->branding ?? [])])->save();
    }
}
