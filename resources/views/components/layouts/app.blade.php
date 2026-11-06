@props(['title' => null])
@php
    $organization = $tenant->organization();
    $solo = $organization?->isSolo() ?? false;
    $navGroups = $organization ? [
        '' => [
            $organization->isActive() ? null : ['Verification', 'app.verification.index', 'settings.manage'],
            ['Dashboard', 'app.dashboard', 'dashboard.view'],
            ['Patients', 'app.patients.index', 'patients.view'],
            ['Appointments', 'app.appointments.index', 'appointments.view'],
            ['Queue', 'app.queue.index', 'appointments.view'],
            ['Patient chats', 'app.chats.index', 'consultations.manage'],
            ['Follow-ups', 'app.follow-ups.index', 'appointments.manage'],
            ['Doctor calendar', 'app.calendar.index', 'appointments.manage'],
            ['Prescriptions', 'app.prescriptions.index', 'prescriptions.view'],
            ['Medicines', 'app.medicines.index', 'prescriptions.manage'],
        ],
        'Money' => [
            ['Billing', 'app.invoices.index', 'billing.view'],
            ['Reports', 'app.reports.index', 'reports.view'],
            ['Fees and services', 'app.billing-settings.index', 'settings.manage'],
        ],
        'Your practice' => [
            ['Staff and doctors', 'app.staff.index', 'staff.manage'],
            [$solo ? 'Chambers' : 'Branches', 'app.locations.index', 'locations.manage'],
            $solo ? null : ['Role permissions', 'app.roles.index', 'roles.manage'],
            ['Website and design', 'app.settings.design', 'settings.manage'],
            ['Booking links', 'app.booking-links.index', 'settings.manage'],
            ['Organization', 'app.settings.organization', 'settings.manage'],
            ['Custom domain', 'app.domains.index', 'settings.manage'],
            ['Integrations', 'app.integrations.index', 'integrations.manage'],
            config('plans.enabled') ? ['Subscription', 'app.subscription.index', 'subscription.manage'] : null,
            $organization->isActive() ? ['Verification', 'app.verification.index', 'settings.manage'] : null,
        ],
        'Help' => [
            ['Audit log', 'app.audit.index', 'audit.view'],
            ['Support', 'app.support.index', 'dashboard.view'],
        ],
    ] : [];
    $user = auth('web')->user();
    $impersonating = session()->has('impersonation.admin_id');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>@include('partials.head', ['title' => $title, 'portal' => 'app'])</head>
<body class="h-full antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">{{ __('Skip to content') }}</a>
<div class="flex min-h-full" x-data="sidebar">
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="close"></div>
    <aside :class="panelClass" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-950 transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center gap-2 px-4">
            <img src="/icons/app-192.png" alt="" class="h-8 w-8 rounded-lg">
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-white">{{ config('app.name') }}</div>
                <div class="truncate text-xs text-brand-300">{{ $organization?->type->label() ?? 'Clinic portal' }}</div>
            </div>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
            @foreach ($navGroups as $heading => $items)
                @php($visible = array_filter($items, fn ($item) => $item && ! \Illuminate\Support\Str::is($disabledRoutes, $item[1]) && \Illuminate\Support\Facades\Gate::check($item[2])))
                @if ($visible)
                    @if ($heading)<p class="px-3 pt-4 pb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $heading }}</p>@endif
                    @foreach ($visible as [$label, $route, $permission])
                        <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($route, str_replace('.index', '.*', $route))])>{{ $label }}</a>
                    @endforeach
                @endif
            @endforeach
            <a href="{{ route('app.account') }}" @class(['nav-link', 'active' => request()->routeIs('app.account')])>My account</a>
        </nav>
        <div class="p-3">@include('partials.install-button', ['class' => 'btn btn-sm btn-secondary w-full'])</div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        @if ($impersonating)
            <div class="flex flex-wrap items-center justify-between gap-2 bg-amber-400 px-4 py-2 text-sm font-medium text-slate-900" role="alert">
                <span>You are viewing as {{ $user?->name }}. Everything you do is recorded against your admin account.</span>
                <form method="POST" action="{{ route('admin.impersonation.stop') }}">@csrf<button class="btn btn-sm btn-secondary">Stop viewing</button></form>
            </div>
        @endif
        <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/90 px-3 backdrop-blur sm:px-5">
            <button type="button" class="btn btn-ghost px-2 lg:hidden" @click="toggle" aria-label="Open menu">&#9776;</button>
            @if ($organization?->icon_192_path)<img src="{{ route('app.settings.design.image', 'logo') }}" alt="" class="h-9 w-9 flex-none rounded-lg object-contain">@endif
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold sm:text-lg">{{ $title }}</h1>
                @if ($organization)
                    <p class="truncate text-xs text-slate-500">
                        {{ $organization->name }}@if ($tenant->location()) · {{ $tenant->location()->name }}@endif
                        @if ($otherMembershipCount > 0 && ! $impersonating)
                            · <a href="{{ route('app.organizations') }}" class="font-medium text-brand-700 underline">Switch</a>
                        @endif
                    </p>
                @endif
            </div>
            <div class="relative" x-data="menu" @click.outside="close">
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white" @click="toggle" aria-label="Account menu">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user?->name ?? '?', 0, 1)) }}</button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                    <div class="truncate border-b border-slate-100 px-4 py-2 text-sm font-medium">{{ $user?->name }}</div>
                    <a href="{{ route('app.account') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">My account</a>
                    <form method="POST" action="{{ route('app.logout') }}">@csrf<button class="block w-full px-4 py-2 text-left text-sm text-rose-700 hover:bg-slate-50">Sign out</button></form>
                </div>
            </div>
        </header>
        <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-3 py-4 sm:px-5 sm:py-6">
            @if ($organization?->isAwaitingApproval())@include('partials.verification-banner', ['organization' => $organization])@endif
            @if ($organization?->accessEndsSoon())
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-900" role="status">Your access ends on {{ $organization->access_until->format('d M Y') }}. Please contact us to renew so your work is not interrupted.</div>
            @endif
            @include('partials.flash')
            @include('partials.announcements')
            {{ $slot }}
        </main>
    </div>
</div>
@include('partials.pwa')
</body>
</html>
