@php
    $firstName = $family->first()?->name ?? $account->name;
    $greetingName = $firstName ? \Illuminate\Support\Str::before($firstName, ' ') : null;
    $actions = [
        ['Book appointment', 'plus', $bookUrl],
        ['Appointments', 'calendar', route('patient.appointments')],
        ['Prescriptions', 'document', route('patient.records').'#prescriptions'],
        ['Bills and receipts', 'card', route('patient.records').'#bills'],
        ['Lab reports', 'flask', route('patient.records').'#reports'],
        ['Notifications', 'bell', route('patient.notifications')],
    ];
@endphp
<x-layouts.patient :title="__('Home')">
    <section class="rounded-2xl bg-linear-to-br from-brand-800 to-brand-600 p-6 text-white sm:p-8">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $greetingName ? __('Hello, :name', ['name' => $greetingName]) : __('Welcome') }}</h1>
        <p class="mt-1 text-white/90">{{ __('Here is your health at a glance.') }}</p>
        <dl class="mt-5 grid max-w-md grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-white/15 p-3"><dd class="text-2xl font-bold">{{ $upcoming->count() }}</dd><dt class="text-xs">{{ __('Upcoming') }}</dt></div>
            <div class="rounded-xl bg-white/15 p-3"><dd class="text-2xl font-bold">{{ $prescriptionCount }}</dd><dt class="text-xs">{{ __('Prescriptions') }}</dt></div>
            <div class="rounded-xl bg-white/15 p-3"><dd class="text-2xl font-bold">{{ $dueCount }}</dd><dt class="text-xs">{{ __('Bills due') }}</dt></div>
        </dl>
    </section>

    <section class="mt-5 grid grid-cols-3 gap-3 lg:grid-cols-6" aria-label="{{ __('Quick actions') }}">
        @foreach ($actions as [$label, $icon, $url])
            @if ($url)
                <a href="{{ $url }}" class="card flex flex-col items-center gap-2 p-3 text-center transition hover:border-brand-400 hover:shadow-md">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-brand-700"><x-icon :name="$icon" class="h-6 w-6" /></span>
                    <span class="text-xs font-medium text-slate-700">{{ __($label) }}</span>
                </a>
            @endif
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2" aria-labelledby="upcoming-heading">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="upcoming-heading" class="text-lg font-semibold text-slate-900">{{ __('Upcoming appointments') }}</h2>
                <a class="text-sm font-medium text-brand-700 underline" href="{{ route('patient.appointments') }}">{{ __('See all') }}</a>
            </div>
            <div class="space-y-3">
                @forelse ($upcoming as $appointment)
                    @include('patient.partials.appointment-card', ['appointment' => $appointment])
                @empty
                    <div class="card p-6 text-center">
                        <p class="font-medium text-slate-900">{{ __('You have no upcoming appointments.') }}</p>
                        @if ($bookUrl)
                            <a class="btn btn-primary mt-3" href="{{ $bookUrl }}">{{ __('Book appointment') }}</a>
                        @else
                            <p class="mt-1 text-sm text-slate-500">{{ __('To book, open the link your clinic gave you.') }}</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </section>
        <aside class="space-y-4">
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Recent prescriptions') }}</h2></div>
                @forelse ($prescriptions as $prescription)
                    <a class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0" href="{{ route('patient.prescriptions.pdf', $prescription->id) }}">
                        <span class="min-w-0"><span class="block truncate font-medium text-slate-900">{{ $prescription->doctor_name }}</span><span class="block truncate text-xs text-slate-500">{{ $prescription->issued_at?->format('d M Y') }} · {{ $prescription->organization?->name }}</span></span>
                        <x-icon name="download" class="h-5 w-5 flex-none text-brand-600" />
                    </a>
                @empty
                    <p class="p-4 text-sm text-slate-500">{{ __('Nothing here yet.') }}</p>
                @endforelse
            </section>
            @if ($family->count() > 1)
                <section class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Family on this account') }}</h2></div>
                    @foreach ($family as $member)
                        <p class="flex items-center gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0"><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}</span><span class="min-w-0 truncate">{{ $member->name }} · {{ $member->organization?->name }}</span></p>
                    @endforeach
                </section>
            @endif
        </aside>
    </div>
</x-layouts.patient>
