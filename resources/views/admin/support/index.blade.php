<x-layouts.admin title="Support tickets">
    <x-page-header title="Support tickets">
        <form method="GET">
            <select class="input w-auto" name="status" data-auto-submit aria-label="Status">
                @foreach (['open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed', 'all' => 'All'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
            </select>
        </form>
    </x-page-header>
    <section class="card">
        @forelse ($tickets as $ticket)
            <a class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0 sm:px-5" href="{{ route('admin.support.show', $ticket->id) }}">
                <span class="min-w-0 truncate">{{ $ticket->organization?->name }} · {{ $ticket->subject }}</span>
                <span class="flex gap-2"><span class="badge badge-slate">{{ ucfirst($ticket->category) }}</span><span class="badge badge-amber">{{ ucfirst($ticket->status) }}</span></span>
            </a>
        @empty
            <x-empty title="No tickets here" />
        @endforelse
    </section>
    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.admin>
