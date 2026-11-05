@php
    $statusTone = match ($appointment->status) {
        \App\Enums\AppointmentStatus::Completed => 'badge-green',
        \App\Enums\AppointmentStatus::Cancelled, \App\Enums\AppointmentStatus::NoShow => 'badge-red',
        default => 'badge-amber',
    };
@endphp
<a href="{{ route('patient.appointments.show', $appointment->id) }}" class="card flex items-stretch overflow-hidden transition hover:shadow-md">
    <div class="flex w-20 flex-none flex-col items-center justify-center bg-brand-50 py-3 text-brand-800">
        <span class="text-xs font-semibold uppercase">{{ $appointment->scheduled_on->format('M') }}</span>
        <span class="text-2xl leading-tight font-bold">{{ $appointment->scheduled_on->format('d') }}</span>
        <span class="text-xs">{{ $appointment->scheduled_on->format('D') }}</span>
    </div>
    <div class="min-w-0 flex-1 p-4">
        <div class="flex items-start justify-between gap-2">
            <p class="truncate font-semibold text-slate-900">{{ $appointment->doctor?->name }}</p>
            <span class="badge flex-none {{ $statusTone }}">{{ __($appointment->status->label()) }}</span>
        </div>
        <p class="truncate text-sm text-slate-600">{{ $appointment->organization?->name }} · {{ $appointment->isOnline() ? __('Video call') : $appointment->location?->name }}</p>
        <p class="mt-1 flex flex-wrap items-center gap-x-3 text-sm text-slate-500">
            @if ($appointment->starts_at)<span class="flex items-center gap-1"><x-icon name="clock" class="h-4 w-4" />{{ $appointment->starts_at->format('h:i A') }}</span>@endif
            <span>{{ __('Token') }} {{ $appointment->token_number }}</span>
            <span class="truncate">{{ $appointment->patient?->name }}</span>
        </p>
    </div>
</a>
