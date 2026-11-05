<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }} · {{ $organization->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.brand-style', ['organization' => $organization])
</head>
<body class="min-h-screen bg-slate-50 text-slate-700 antialiased">
    <header class="border-b border-slate-200 bg-white print:hidden">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                @if ($organization->icon_192_path && $organization->isActive())
                    <img src="{{ route('brand.icon', [$organization->slug, 192]) }}" alt="" class="h-10 w-10 flex-none rounded-lg object-contain">
                @else
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-600 font-bold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($organization->name, 0, 1)) }}</span>
                @endif
                <span class="truncate font-semibold text-slate-900">{{ $organization->name }}</span>
            </div>
            <div class="flex gap-2">
                <a class="btn btn-primary" href="{{ $pdfUrl }}"><x-icon name="download" class="h-4 w-4" />{{ __('Download PDF') }}</a>
                <button type="button" class="btn btn-secondary" data-print>{{ __('Print') }}</button>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-5">
        <article class="card card-body print:border-0 print:shadow-none">
            @include($partial)
        </article>
        <p class="mt-4 text-center text-sm text-slate-500 print:hidden">{{ __('This link works until :date. Please do not share it with anyone you would not show this to.', ['date' => $expires->format('d M Y')]) }}</p>
        <p class="mt-1 text-center text-sm print:hidden"><a class="text-brand-700 underline" href="{{ route('patient.login') }}">{{ __('Sign in to see all your records') }}</a></p>
    </main>
</body>
</html>
