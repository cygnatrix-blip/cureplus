@props(['title' => null])
@php
    $admin = auth('platform')->user();
    $nav = [
        ['Dashboard', 'admin.dashboard', null],
        ['Organizations', 'admin.organizations.index', 'platform.organizations.view'],
        ['Doctors', 'admin.doctors.index', 'platform.doctors.manage'],
        ['Plans and subscriptions', 'admin.plans.index', 'platform.plans.manage'],
        ['Medicine catalogue', 'admin.medicines.index', 'platform.catalogue.manage'],
        ['Templates', 'admin.templates.index', 'platform.content.manage'],
        ['Custom domains', 'admin.domains.index', 'platform.domains.manage'],
        ['Reports', 'admin.reports.index', 'platform.reports.view'],
        ['Support tickets', 'admin.support.index', 'platform.support.manage'],
        ['Announcements', 'admin.announcements.index', 'platform.content.manage'],
        ['Feature flags', 'admin.feature-flags.index', 'platform.flags.manage'],
        ['Data requests', 'admin.data-requests.index', 'platform.privacy.manage'],
        ['Audit log', 'admin.audit.index', 'platform.audit.view'],
        ['Security center', 'admin.security.index', 'platform.security.manage'],
        ['Breach incidents', 'admin.breaches.index', 'platform.breaches.manage'],
        ['Backups', 'admin.backups.index', 'platform.backups.manage'],
        ['Integrations', 'admin.integrations.index', 'platform.integrations.manage'],
        ['API keys', 'admin.api-keys.index', 'platform.api-keys.manage'],
        ['System configuration', 'admin.system.index', 'platform.system.manage'],
        ['Monitoring', 'admin.monitoring.index', 'platform.monitoring.view'],
        ['IT admins', 'admin.admins.index', 'platform.admins.manage'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>@include('partials.head', ['title' => $title, 'portal' => 'admin'])</head>
<body class="h-full antialiased">
<div class="flex min-h-full" x-data="sidebar">
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="close"></div>
    <aside :class="panelClass" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-950 transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center gap-2 px-4">
            <img src="/icons/admin-192.png" alt="" class="h-8 w-8 rounded-lg">
            <div>
                <div class="text-sm font-semibold text-white">{{ config('app.name') }}</div>
                <div class="text-xs text-amber-400">IT Admin Portal</div>
            </div>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
            @foreach ($nav as [$label, $route, $ability])
                @if (! $ability || $admin?->can($ability))
                    <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($route, str_replace('.index', '.*', $route))])>{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <div class="p-3">@include('partials.install-button', ['class' => 'btn btn-sm btn-secondary w-full'])</div>
    </aside>
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/90 px-3 backdrop-blur sm:px-5">
            <button type="button" class="btn btn-ghost px-2 lg:hidden" @click="toggle" aria-label="Open menu">&#9776;</button>
            <h1 class="min-w-0 flex-1 truncate text-base font-semibold sm:text-lg">{{ $title }}</h1>
            <span class="hidden text-sm text-slate-500 sm:inline">{{ $admin?->name }} · {{ $admin?->roleName() }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-ghost">Sign out</button></form>
        </header>
        <main class="mx-auto w-full max-w-7xl flex-1 px-3 py-4 sm:px-5 sm:py-6">
            @include('partials.flash')
            {{ $slot }}
        </main>
    </div>
</div>
@include('partials.pwa')
</body>
</html>
