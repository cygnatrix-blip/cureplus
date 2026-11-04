<x-layouts.app title="Dashboard">
    <x-page-header :title="$scopeLabel" :subtitle="now()->format('l, d M Y')">
        @can('create', \App\Models\Patient::class)<a class="btn btn-secondary" href="{{ route('app.patients.create') }}">New patient</a>@endcan
        @can('appointments.manage')<a class="btn btn-primary" href="{{ route('app.appointments.create') }}">Book</a>@endcan
    </x-page-header>
    @include('partials.setup-checklist')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @if ($canSeeAppointments)
            <x-stat label="Appointments" :value="$appointmentsToday" />
            <x-stat label="Waiting now" :value="$waiting" :tone="$waiting > 10 ? 'warn' : null" />
            <x-stat label="Seen" :value="$seen" />
        @endif
        @if ($collections !== null)
            <x-stat label="Collected today" :value="\App\Support\Money::format($collections)" />
        @endif
        @if ($followUpsDue !== null)
            <x-stat label="Follow-ups due" :value="$followUpsDue" />
            <x-stat label="Missed follow-ups" :value="$missedFollowUps" :tone="$missedFollowUps ? 'warn' : null" />
        @endif
        @if ($canManageStaff)
            <x-stat :label="$tenant->organization()->isSolo() ? 'Active chambers' : 'Active branches'" :value="$locationCount" />
            <x-stat label="Team members" :value="$staffCount" />
        @endif
    </div>
    @if ($canSeeAppointments)
        <section class="card mt-4">
            <div class="card-header">
                <h2 class="card-title">Next up</h2>
                <a class="text-sm font-medium text-brand-700 underline" href="{{ route('app.queue.index') }}">Open queue</a>
            </div>
            @forelse ($nextUp as $appointment)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                    <span class="min-w-0 truncate"><span class="font-semibold">{{ $appointment->token_number }}</span> · {{ $appointment->patient?->name }} · {{ $appointment->doctor?->name }}</span>
                    <span class="badge badge-slate">{{ $appointment->starts_at?->format('H:i') ?? 'Walk-in' }}</span>
                </div>
            @empty
                <x-empty title="Nobody is waiting" />
            @endforelse
        </section>
    @endif
</x-layouts.app>
