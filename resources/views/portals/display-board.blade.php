<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Queue · {{ $location->name }}</title>
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="{{ route('display.manifest', $token) }}">
    <link rel="icon" href="/icons/display-192.png" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-950 text-white antialiased">
    <main class="p-6" x-data="queueDisplay" data-url="{{ route('display.data', $token) }}">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-semibold">{{ $location->name }}</h1>
            <button type="button" class="btn btn-secondary" @click="fullscreen" x-show="canFullscreen">Full screen</button>
        </header>
        <p x-show="offline" x-cloak role="status" class="mb-4 rounded-lg bg-amber-500 p-3 text-slate-900">Connection lost. Retrying…</p>
        <p x-show="isEmpty" x-cloak class="text-2xl text-slate-400">No tokens in the queue.</p>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="doctor in doctors" :key="doctor.id">
                <section class="rounded-2xl bg-slate-900 p-6">
                    <h2 class="text-xl text-slate-300" x-text="doctor.name"></h2>
                    <p class="mt-4 text-sm tracking-wide text-slate-400 uppercase">Now serving</p>
                    <p class="text-7xl font-bold text-brand-300" x-text="doctor.nowLabel"></p>
                    <p class="mt-4 text-sm tracking-wide text-slate-400 uppercase">Next</p>
                    <p class="text-3xl font-semibold" x-text="doctor.nextLabel"></p>
                </section>
            </template>
        </div>
    </main>
    @include('partials.pwa')
</body>
</html>
