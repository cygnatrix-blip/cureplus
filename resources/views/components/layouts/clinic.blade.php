@props(['organization'])
@php
    $tagline = $organization->branding['tagline'] ?? null;
    $about = $organization->branding['about'] ?? null;
    $description = \Illuminate\Support\Str::limit($about ?: ($tagline ?: __('Book your appointment at :clinic', ['clinic' => $organization->name])), 155);
    $phone = $organization->branding['contact_phone'] ?? null;
    $email = $organization->branding['contact_email'] ?? null;
    $icon = $organization->icon_192_path ? route('brand.icon', [$organization->slug, 192]) : null;
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'MedicalClinic',
        'name' => $organization->name,
        'url' => route('clinic.home', $organization->slug),
        'description' => $description,
        'telephone' => $phone,
        'email' => $email,
        'logo' => $icon,
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full scroll-smooth">
<head>
    @include('partials.head', ['title' => null, 'fullTitle' => $organization->name.($tagline ? ' · '.$tagline : ''), 'indexable' => true, 'description' => $description, 'favicon' => $icon, 'portal' => 'patient', 'manifest' => route('brand.manifest', $organization->slug), 'theme' => $organization->brand_color])
    @include('partials.brand-style', ['organization' => $organization])
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($schema)</script>
</head>
<body class="min-h-full bg-white pb-20 text-slate-700 antialiased lg:pb-0">
    {{ $slot }}
    @include('partials.pwa')
</body>
</html>
