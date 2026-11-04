<x-layouts.app title="Your organizations">
    <div class="mx-auto max-w-2xl space-y-2">
        @foreach ($memberships as $membership)
            <form method="POST" action="{{ route('app.organizations.switch', $membership->organization->uuid) }}" class="card card-body flex items-center justify-between gap-3">
                @csrf
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-900">{{ $membership->organization->name }}</p>
                    <p class="text-sm text-slate-500">{{ $membership->roles->pluck('name')->join(', ') }}</p>
                </div>
                @if ($membership->organization_id === $activeId)
                    <span class="badge badge-green">Current</span>
                @else
                    <button class="btn btn-secondary">Switch</button>
                @endif
            </form>
        @endforeach
    </div>
</x-layouts.app>
