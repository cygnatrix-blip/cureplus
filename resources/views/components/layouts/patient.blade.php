@props(['title' => null])
@php
    $account = auth('patient')->user();
    $brand = app(\App\Services\Portal\PortalBrand::class)->current();
    $nav = ['patient.home' => ['Home', 'home'], 'patient.appointments' => ['Appointments', 'calendar'], 'patient.records' => ['Records', 'document'], 'patient.consents' => ['Sharing', 'share'], 'patient.privacy' => ['Privacy', 'shield']];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    @include('partials.head', ['title' => $title, 'portal' => 'patient', 'manifest' => $brand ? route('brand.manifest', $brand->slug) : null, 'theme' => $brand?->brand_color])
    @include('partials.brand-style', ['organization' => $brand])
</head>
<body class="min-h-full bg-slate-50 pb-20 antialiased lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">{{ __('Skip to content') }}</a>
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4">
            <a href="{{ $account ? route('patient.home') : route('patient.login') }}" class="flex min-w-0 items-center gap-3">
                @if ($brand?->icon_192_path)
                    <img src="{{ route('brand.icon', [$brand->slug, 192]) }}" alt="" class="h-9 w-9 flex-none rounded-lg object-contain">
                @elseif ($brand)
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-600 font-bold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($brand->name, 0, 1)) }}</span>
                @else
                    <img src="/icons/patient-192.png" alt="" class="h-9 w-9 flex-none rounded-lg">
                @endif
                <span class="truncate font-semibold text-slate-900">{{ $brand?->name ?? config('cureplus.portals.patient.short_name') }}</span>
            </a>
            @if ($account)
                <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                    @foreach ($nav as $route => [$label, $icon])
                        <a href="{{ route($route) }}" @class(['flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium', 'bg-brand-50 text-brand-700' => request()->routeIs($route), 'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($route)])><x-icon :name="$icon" />{{ __($label) }}</a>
                    @endforeach
                </nav>
            @endif
            <div class="flex flex-none items-center gap-1">
                @include('partials.language')
                @if ($account)<form method="POST" action="{{ route('patient.logout') }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('Sign out') }}</button></form>@endif
            </div>
        </div>
    </header>
    <main id="main" class="mx-auto max-w-6xl px-4 py-5">
        @include('partials.flash')
        {{ $slot }}
        <div class="mt-6">@include('partials.install-button')</div>
    </main>
    @if ($account)
        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Main">
            @foreach ($nav as $route => [$label, $icon])
                <a href="{{ route($route) }}" @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-[11px]', 'font-semibold text-brand-700' => request()->routeIs($route), 'text-slate-500' => ! request()->routeIs($route)])><x-icon :name="$icon" />{{ __($label) }}</a>
            @endforeach
        </nav>
    @endif
    @include('partials.pwa')
</body>
</html>
