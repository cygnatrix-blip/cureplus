@php($status = \App\Enums\AppointmentStatus::class)
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
    <div class="flex min-w-0 items-center gap-3">
        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-slate-900 font-semibold text-white">{{ $appointment->token_number }}</span>
        <div class="min-w-0">
            <p class="truncate font-medium text-slate-900">{{ $appointment->patient?->name }}</p>
            <p class="text-xs text-slate-500">@if ($showDate ?? false){{ $appointment->scheduled_on->format('d M') }} · {{ $appointment->doctor?->name }} · @endif{{ $appointment->starts_at?->format('H:i') ?? 'Walk-in' }} · {{ $appointment->status->label() }}@if ($appointment->isOnline()) · <span class="font-semibold text-brand-700">Video</span>@endif @if ($appointment->isAwaitingPayment()) · <span class="font-semibold text-amber-700">Awaiting payment</span>@endif</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-1">
        @can('appointments.manage')
            @if ($appointment->status === $status::Booked && ! $appointment->isOnline() && ! ($showDate ?? false))
                <form method="POST" action="{{ route('app.queue.move', $appointment) }}" data-optimistic>@csrf<input type="hidden" name="status" value="checked_in"><button class="btn btn-sm btn-secondary">Check in</button></form>
            @endif
            @if (in_array($appointment->status, [$status::Booked, $status::CheckedIn], true))
                <form method="POST" action="{{ route('app.queue.move', $appointment) }}" data-optimistic>@csrf<input type="hidden" name="status" value="no_show"><button class="btn btn-sm btn-ghost">No show</button></form>
            @endif
        @endcan
        @if ($appointment->canJoinVideo() && ! ($showDate ?? false))
            <x-send-to-patient :patient="$appointment->patient" event="video_consult_now" :link="$appointment->video_link" :variables="['name' => $appointment->patient?->name, 'doctor' => $appointment->doctor?->name, 'link' => $appointment->video_link]" />
            @include('app.appointments.partials.join-video')
        @elseif ($appointment->status === $status::CheckedIn && ! ($showDate ?? false))
            <x-send-to-patient :patient="$appointment->patient" event="token_called" :variables="['name' => $appointment->patient?->name, 'token' => $appointment->token_number, 'doctor' => $appointment->doctor?->name]" />
        @endif
        @can('chat', $appointment)
            <a class="btn btn-sm btn-secondary" href="{{ route('app.appointments.chat', $appointment) }}">Chat
                @if ($appointment->unread_messages_count ?? 0)<span class="badge badge-amber ml-1">{{ $appointment->unread_messages_count }}</span>@endif
            </a>
        @endcan
        @can('billing.manage')<a class="btn btn-sm btn-secondary" href="{{ route('app.invoices.create', ['appointment' => $appointment->id]) }}">Bill</a>@endcan
        @if (in_array($appointment->status, [$status::Booked, $status::CheckedIn, $status::InConsultation], true))
            @can('start', [\App\Models\Visit::class, $appointment])
                <form method="POST" action="{{ route('app.visits.start', $appointment) }}">@csrf<button class="btn btn-sm btn-primary">{{ $appointment->status === $status::InConsultation ? 'Open' : (auth('web')->user()?->can('consultations.manage') ? 'Start' : 'Vitals') }}</button></form>
            @endcan
        @endif
    </div>
</div>
