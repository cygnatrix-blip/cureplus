@php($status = \App\Enums\AppointmentStatus::class)
@if ($groups->isEmpty())
    <div class="card"><x-empty title="The queue is empty" text="Book or add a walk-in to start." /></div>
@endif
<div class="grid gap-4 lg:grid-cols-2">
    @foreach ($groups as $rows)
        <section class="card">
            <div class="card-header"><h3 class="card-title">{{ $rows->first()->doctor?->name }}</h3><span class="text-xs text-slate-500">{{ $rows->count() }} today</span></div>
            @foreach ($rows as $appointment)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-slate-900 font-semibold text-white">{{ $appointment->token_number }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ $appointment->patient?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $appointment->starts_at?->format('H:i') ?? 'Walk-in' }} · {{ $appointment->status->label() }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @can('appointments.manage')
                            @if ($appointment->status === $status::Booked)
                                <form method="POST" action="{{ route('app.queue.move', $appointment) }}" data-optimistic>@csrf<input type="hidden" name="status" value="checked_in"><button class="btn btn-sm btn-secondary">Check in</button></form>
                            @endif
                            @if (in_array($appointment->status, [$status::Booked, $status::CheckedIn], true))
                                <form method="POST" action="{{ route('app.queue.move', $appointment) }}" data-optimistic>@csrf<input type="hidden" name="status" value="no_show"><button class="btn btn-sm btn-ghost">No show</button></form>
                            @endif
                        @endcan
                        @if ($appointment->status === $status::CheckedIn)
                            <x-send-to-patient :patient="$appointment->patient" event="token_called" :variables="['name' => $appointment->patient?->name, 'token' => $appointment->token_number, 'doctor' => $appointment->doctor?->name]" />
                        @endif
                        @can('billing.manage')<a class="btn btn-sm btn-secondary" href="{{ route('app.invoices.create', ['appointment' => $appointment->id]) }}">Bill</a>@endcan
                        @if (in_array($appointment->status, [$status::Booked, $status::CheckedIn, $status::InConsultation], true))
                            @can('start', [\App\Models\Visit::class, $appointment])
                                <form method="POST" action="{{ route('app.visits.start', $appointment) }}">@csrf<button class="btn btn-sm btn-primary">{{ $appointment->status === $status::InConsultation ? 'Open' : (auth('web')->user()?->can('consultations.manage') ? 'Start' : 'Vitals') }}</button></form>
                            @endcan
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach
</div>
