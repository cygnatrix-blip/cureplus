<x-layouts.app title="Follow-ups">
    <x-page-header title="Follow-ups">
        <a @class(['btn', 'btn-primary' => $view === 'due', 'btn-secondary' => $view !== 'due']) href="{{ route('app.follow-ups.index') }}">Due soon</a>
        <a @class(['btn', 'btn-primary' => $view === 'missed', 'btn-secondary' => $view !== 'missed']) href="{{ route('app.follow-ups.index', ['view' => 'missed']) }}">Missed: call list</a>
    </x-page-header>
    <section class="card">
        @forelse ($followUps as $followUp)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                <div class="min-w-0">
                    <a class="font-medium text-brand-700 underline" href="{{ route('app.patients.show', $followUp->patient_id) }}">{{ $followUp->patient?->name }}</a>
                    <a class="ml-2 text-slate-700 underline" href="tel:{{ $followUp->patient?->mobile }}">{{ $followUp->patient?->mobile }}</a>
                    <p class="text-slate-500">Due {{ $followUp->due_on->format('d M Y') }} · {{ $followUp->doctor?->name }} {{ $followUp->call_notes ? '· '.$followUp->call_notes : '' }}</p>
                </div>
                <form method="POST" action="{{ route('app.follow-ups.update', $followUp) }}" class="flex flex-wrap gap-1">
                    @csrf @method('PUT')
                    <input class="input min-h-9 w-40 py-1" name="call_notes" placeholder="Call notes" aria-label="Call notes">
                    <button class="btn btn-sm btn-secondary" name="status" value="called">Called</button>
                    <button class="btn btn-sm btn-secondary" name="status" value="done">Done</button>
                    <button class="btn btn-sm btn-ghost" name="status" value="cancelled">Dismiss</button>
                </form>
            </div>
        @empty
            <x-empty :title="$view === 'missed' ? 'Nobody to call' : 'No follow-ups due'" />
        @endforelse
    </section>
    <div class="mt-4">{{ $followUps->links() }}</div>
</x-layouts.app>
