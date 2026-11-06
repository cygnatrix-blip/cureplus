<x-layouts.app :title="'Chat with '.$appointment->patient?->name">
    <x-page-header :title="'Chat with '.$appointment->patient?->name" :subtitle="$appointment->scheduled_on->format('d M Y').($appointment->starts_at ? ', '.$appointment->starts_at->format('h:i A') : '').' · '.$appointment->status->label()">
        <a class="btn btn-ghost" href="{{ route('app.chats.index') }}">All chats</a>
        @if ($canClose)
            <form method="POST" action="{{ route('app.appointments.chat.close', $appointment) }}" data-confirm="Close this chat? The patient will no longer be able to send messages.">@csrf<button class="btn btn-secondary">Close chat</button></form>
        @else
            <form method="POST" action="{{ route('app.appointments.chat.reopen', $appointment) }}">@csrf<button class="btn btn-secondary">Reopen chat</button></form>
        @endif
    </x-page-header>
    <section class="card mx-auto max-w-3xl overflow-hidden">
        @if ($canClose && $isFinished)
            <p class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600">This appointment is finished. Close the chat when the patient no longer needs it. It closes by itself {{ \App\Services\AppointmentChat::AUTO_CLOSE_DAYS }} days after the appointment.</p>
        @endif
        <div class="max-h-[60vh] min-h-64 space-y-3 overflow-y-auto p-4" data-chat-thread="{{ route('app.appointments.chat.messages', $appointment) }}" aria-live="polite">
            @include('chat.messages', ['viewer' => \App\Models\AppointmentMessage::FROM_DOCTOR])
        </div>
        @include('chat.composer', ['open' => $appointment->isChatOpen(), 'action' => route('app.appointments.chat.store', $appointment)])
    </section>
</x-layouts.app>
