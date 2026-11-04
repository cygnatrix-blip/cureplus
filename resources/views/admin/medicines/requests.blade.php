<x-layouts.admin title="Catalogue requests">
    <div class="card">
        @forelse ($requests as $item)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                <span>{{ $item->generic_name }} · {{ $item->brand_name }} · {{ $item->strength }} · {{ $item->form }}</span>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.medicines.requests.approve', $item->id) }}">@csrf<button class="btn btn-sm btn-primary">Approve</button></form>
                    <form method="POST" action="{{ route('admin.medicines.requests.reject', $item->id) }}" class="flex gap-1">
                        @csrf
                        <input class="input min-h-9 w-40 py-1" name="reason" placeholder="Reason" required aria-label="Reason for rejecting">
                        <button class="btn btn-sm btn-danger">Reject</button>
                    </form>
                </div>
            </div>
        @empty
            <x-empty title="No pending requests" />
        @endforelse
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.admin>
