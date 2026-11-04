<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<meta name="robots" content="noindex">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ ($theme ?? null) ?: config("cureplus.portals.{$portal}.theme_color") }}">
<link rel="manifest" href="{{ $manifest ?? route('pwa.manifest', $portal) }}">
<link rel="icon" href="/icons/{{ config("cureplus.portals.{$portal}.icon") }}-192.png" type="image/png">
<link rel="apple-touch-icon" href="/icons/{{ config("cureplus.portals.{$portal}.icon") }}-192.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config("cureplus.portals.{$portal}.short_name") }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
