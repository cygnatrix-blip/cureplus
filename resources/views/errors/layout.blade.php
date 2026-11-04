<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }}</title>
    @if (is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="flex min-h-full items-center justify-center px-4 antialiased">
    <main class="text-center">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $heading }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
    </main>
</body>
</html>
