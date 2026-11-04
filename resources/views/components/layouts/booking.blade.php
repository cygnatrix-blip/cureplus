@props(['organization', 'title' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    @include('partials.head', ['title' => $title ?? $organization->name, 'portal' => 'patient', 'manifest' => route('brand.manifest', $organization->slug), 'theme' => $organization->brand_color])
    @include('partials.brand-style', ['organization' => $organization])
</head>
<body class="flex min-h-full flex-col bg-slate-50 antialiased">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4">
            <a href="{{ route('clinic.home', $organization->slug) }}" class="flex min-w-0 items-center gap-3">
                @if ($organization->icon_192_path)
                    <img src="{{ route('brand.icon', [$organization->slug, 192]) }}" alt="" class="h-10 w-10 flex-none rounded-lg object-contain">
                @else
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-600 text-lg font-bold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($organization->name, 0, 1)) }}</span>
                @endif
                <span class="truncate text-lg font-semibold text-slate-900">{{ $organization->name }}</span>
            </a>
            <div class="flex flex-none items-center gap-2">
                @include('partials.language')
                @auth('patient')
                    <a class="btn btn-sm btn-secondary" href="{{ route('patient.home') }}">{{ __('My account') }}</a>
                @else
                    <a class="btn btn-sm btn-secondary" href="{{ route('patient.login') }}">{{ __('Sign in') }}</a>
                @endauth
            </div>
        </div>
    </header>
    <div class="flex-1">{{ $slot }}</div>
    <footer class="mt-10 border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-slate-500">
            <p>{{ $organization->name }}</p>
            <div class="flex flex-wrap items-center gap-4">
                <a class="underline" href="{{ route('privacy') }}">{{ __('Privacy') }}</a>
                <a class="underline" href="{{ route('terms') }}">{{ __('Terms') }}</a>
                @include('partials.install-button', ['class' => 'btn btn-sm btn-secondary'])
            </div>
        </div>
    </footer>
    @include('partials.pwa')
</body>
</html>
