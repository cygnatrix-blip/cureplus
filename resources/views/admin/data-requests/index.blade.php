<x-layouts.admin title="Data requests">
    <x-page-header title="Patient data requests" subtitle="DPDP correction and deletion requests." />
    <div class="card">
        @forelse ($requests as $item)
            <div class="border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                <p class="font-medium text-slate-900">{{ ucfirst($item->type) }} · {{ ucfirst($item->status) }} · {{ $item->created_at?->format('d M Y') }}</p>
                <p class="text-slate-600">{{ \App\Support\Mask::identifier($item->account?->email) }}</p>
                @if ($item->details)<p class="mt-1">{{ $item->details }}</p>@endif
                @if ($item->status === 'open')
                    <form method="POST" action="{{ route('admin.data-requests.resolve', $item) }}" class="mt-2 space-y-2" @if ($item->type === 'deletion') data-confirm="Completing this erases the patient's login and unlinks their records. Continue?" @endif>
                        @csrf
                        <textarea class="input" name="resolution_notes" rows="2" placeholder="What was done" required aria-label="Resolution notes"></textarea>
                        <div class="flex gap-2">
                            <button class="btn btn-sm btn-primary" name="status" value="completed">Complete</button>
                            <button class="btn btn-sm btn-secondary" name="status" value="rejected">Reject</button>
                        </div>
                    </form>
                @elseif ($item->resolution_notes)
                    <p class="mt-1 text-slate-600">{{ $item->resolution_notes }}</p>
                @endif
            </div>
        @empty
            <x-empty title="No requests" />
        @endforelse
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.admin>
