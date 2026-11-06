<x-layouts.patient :title="__('Messages')">
    <div class="mx-auto max-w-3xl space-y-4">
        <a class="text-sm font-medium text-brand-700 underline" href="{{ route('patient.appointments.show', $appointment->id) }}">{{ __('Back to appointment') }}</a>
        <section class="card overflow-hidden">
            <header class="border-b border-slate-200 p-4">
                <h1 class="font-semibold text-slate-900">{{ __('Messages with :doctor', ['doctor' => $appointment->doctor?->name]) }}</h1>
                <p class="text-sm text-slate-600">{{ $appointment->organization?->name }} · {{ $appointment->scheduled_on->format('d M Y') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('Only your doctor reads these messages. In an emergency, call the clinic or go to a hospital.') }}</p>
            </header>
            <div class="max-h-[60vh] min-h-64 space-y-3 overflow-y-auto p-4" data-chat-thread="{{ route('patient.appointments.chat.messages', $appointment->id) }}" aria-live="polite">
                @include('chat.messages', ['viewer' => \App\Models\AppointmentMessage::FROM_PATIENT])
            </div>
            @include('chat.composer', ['open' => $appointment->isChatOpen(), 'action' => route('patient.appointments.chat.store', $appointment->id)])
        </section>
    </div>
</x-layouts.patient>
