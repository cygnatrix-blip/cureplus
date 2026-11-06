<x-layouts.app title="Patient chats">
    <x-page-header title="Patient chats" subtitle="Open chats with your patients. Only you can read them." />
    <section class="card mx-auto max-w-3xl">
        @forelse ($appointments as $appointment)
            <a href="{{ route('app.appointments.chat', $appointment) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 hover:bg-slate-50">
                <span class="min-w-0">
                    <span class="block truncate font-medium text-slate-900">{{ $appointment->patient?->name }}</span>
                    <span class="block text-xs text-slate-500">{{ $appointment->patient?->patient_code }} · {{ $appointment->scheduled_on->format('d M Y') }} · {{ $appointment->status->label() }}</span>
                </span>
                @if ($appointment->unread_count)
                    <span class="badge badge-amber flex-none">{{ $appointment->unread_count }} new</span>
                @endif
            </a>
        @empty
            <x-empty title="No open chats" text="Patients can message you from their appointment page." />
        @endforelse
    </section>
</x-layouts.app>
