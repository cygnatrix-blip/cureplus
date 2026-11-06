{{-- Shared by the doctor's and the patient's chat, and returned alone when the page refreshes the thread. --}}
@forelse ($messages as $message)
    @php($mine = $message->sender_type === $viewer)
    @php($fileUrl = $message->hasAttachment()
        ? ($viewer === \App\Models\AppointmentMessage::FROM_DOCTOR
            ? route('app.appointments.chat.file', [$appointment, $message])
            : route('patient.appointments.chat.file', [$appointment->id, $message->id]))
        : null)
    <div @class(['flex', 'justify-end' => $mine, 'justify-start' => ! $mine]) data-message-id="{{ $message->id }}">
        <div @class(['max-w-[85%] space-y-1 rounded-2xl px-4 py-2 text-sm sm:max-w-[70%]', 'rounded-br-sm bg-brand-600 text-white' => $mine, 'rounded-bl-sm bg-slate-100 text-slate-900' => ! $mine])>
            <p @class(['text-xs font-semibold', 'text-white/80' => $mine, 'text-slate-500' => ! $mine])>{{ $message->isFromPatient() ? ($appointment->patient?->name ?? __('Patient')) : __('Doctor') }}</p>
            @if ($message->body !== null)
                <p class="break-words whitespace-pre-line">{{ $message->body }}</p>
            @endif
            @if ($fileUrl)
                @if ($message->isImage())
                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener"><img src="{{ $fileUrl }}" alt="{{ $message->attachment_name }}" class="max-h-48 rounded-lg" loading="lazy"></a>
                @else
                    <a href="{{ $fileUrl }}" @class(['flex items-center gap-2 rounded-lg px-3 py-2 underline', 'bg-white/15' => $mine, 'bg-white' => ! $mine])><x-icon name="document" class="h-4 w-4 flex-none" /><span class="truncate">{{ $message->attachment_name }}</span></a>
                @endif
            @endif
            <p @class(['text-[11px]', 'text-white/70 text-right' => $mine, 'text-slate-500' => ! $mine])>
                {{ $message->created_at->format('d M, h:i A') }}@if ($mine && $message->read_at) · {{ __('Seen') }}@endif
            </p>
        </div>
    </div>
@empty
    <p class="py-8 text-center text-sm text-slate-500">{{ __('No messages yet.') }}</p>
@endforelse
