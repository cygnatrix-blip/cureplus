<x-layouts.admin title="Breach incidents">
    <x-page-header title="Personal data breaches" subtitle="DPDP Act: the Data Protection Board and affected people must be told. Confirm the timelines with counsel." />
    <form method="POST" action="{{ route('admin.breaches.store') }}" class="card card-body max-w-2xl space-y-3">
        @csrf
        <h2 class="font-semibold text-slate-900">Record an incident</h2>
        <x-field name="title" label="Title" required />
        <div>
            <label class="label" for="description">What happened</label>
            <textarea class="input" id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
            @error('description')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="label" for="severity">Severity</label><select class="input" id="severity" name="severity">@foreach (['low', 'medium', 'high', 'critical'] as $level)<option value="{{ $level }}">{{ ucfirst($level) }}</option>@endforeach</select></div>
            <x-field name="detected_at" label="Detected" type="datetime-local" required />
            <x-field name="affected_count" label="People affected" type="number" min="0" />
        </div>
        <button class="btn btn-primary">Record</button>
    </form>
    <div class="mt-4 space-y-3">
        @forelse ($incidents as $incident)
            <form method="POST" action="{{ route('admin.breaches.update', $incident) }}" class="card card-body space-y-2">
                @csrf @method('PUT')
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-slate-900">{{ $incident->title }}</p>
                    <span class="badge badge-amber">{{ ucfirst($incident->severity) }}</span>
                </div>
                <p class="text-sm text-slate-600">{{ $incident->description }}</p>
                <p class="text-xs text-slate-500">Detected {{ $incident->detected_at->format('d M Y H:i') }}@if ($incident->board_notified_at) · Board told {{ $incident->board_notified_at->format('d M Y H:i') }}@endif @if ($incident->patients_notified_at) · People told {{ $incident->patients_notified_at->format('d M Y H:i') }}@endif</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <select class="input" name="status" aria-label="Status">@foreach (['open', 'contained', 'resolved'] as $status)<option value="{{ $status }}" @selected($incident->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    <input class="input" type="number" min="0" name="affected_count" value="{{ $incident->affected_count }}" placeholder="People affected" aria-label="People affected">
                </div>
                <textarea class="input" name="notes" rows="2" placeholder="Notes" aria-label="Notes">{{ $incident->notes }}</textarea>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="board_notified" value="1" @checked($incident->board_notified_at)> Board notified</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="patients_notified" value="1" @checked($incident->patients_notified_at)> People notified</label>
                    <button class="btn btn-sm btn-secondary">Save</button>
                </div>
            </form>
        @empty
            <div class="card"><x-empty title="No incidents recorded" /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $incidents->links() }}</div>
</x-layouts.admin>
