<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $fullTitle ?? ($title ? $title.' · ' : '').config('app.name') }}</title>
@if ($indexable ?? false)
<meta name="description" content="{{ $description ?? '' }}">
<meta property="og:title" content="{{ $fullTitle ?? config('app.name') }}">
<meta property="og:description" content="{{ $description ?? '' }}">
<meta property="og:type" content="website">
@else
<meta name="robots" content="noindex">
@endif
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ ($theme ?? null) ?: config("cureplus.portals.{$portal}.theme_color") }}">
<link rel="manifest" href="{{ $manifest ?? route('pwa.manifest', $portal) }}">
<link rel="icon" href="{{ ($favicon ?? null) ?: '/icons/'.config("cureplus.portals.{$portal}.icon").'-192.png' }}" type="image/png">
<link rel="apple-touch-icon" href="/icons/{{ config("cureplus.portals.{$portal}.icon") }}-192.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config("cureplus.portals.{$portal}.short_name") }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
