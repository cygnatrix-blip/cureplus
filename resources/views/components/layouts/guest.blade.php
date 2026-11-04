@props(['title' => null, 'portal' => 'app', 'wide' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>@include('partials.head', ['title' => $title, 'portal' => $portal])</head>
<body class="flex min-h-full flex-col items-center justify-center px-4 py-8 antialiased">
    <div @class(['w-full', 'max-w-xl' => $wide, 'max-w-md' => ! $wide])>
        <a href="{{ route('home') }}" class="mb-6 flex items-center justify-center gap-2">
            <img src="/icons/{{ config("cureplus.portals.{$portal}.icon") }}-192.png" alt="" class="h-10 w-10 rounded-lg">
            <span class="text-lg font-semibold text-slate-900">{{ config('app.name') }}</span>
        </a>
        @include('partials.flash')
        <div class="card card-body">{{ $slot }}</div>
    </div>
    @include('partials.pwa')
</body>
</html>
