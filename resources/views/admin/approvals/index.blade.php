<x-layouts.admin title="Pending approvals">
    <x-page-header title="Pending approvals" subtitle="Oldest first." />
    <div class="card">
        @if ($pending->isEmpty())
            <x-empty title="Nothing is waiting" text="New signups appear here." />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th>Type</th><th>Waiting</th><th>Documents</th><th></th></tr></thead>
                <tbody>
                    @foreach ($pending as $organization)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $organization->name }} @if ($organization->resubmitted_at)<span class="badge badge-slate">Sent again</span>@endif</td>
                            <td>{{ $organization->type->label() }}</td>
                            <td>{{ ($organization->resubmitted_at ?? $organization->created_at)?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</td>
                            <td>@if ($verification->isComplete($organization))<span class="badge badge-green">Ready to review</span>@else<span class="badge badge-amber">Incomplete</span>@endif</td>
                            <td class="text-right"><a class="btn btn-sm btn-primary" href="{{ route('admin.approvals.show', $organization) }}">Review</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $pending->links() }}</div>

    @if ($rejected->isNotEmpty())
        <section class="card mt-4">
            <div class="card-header"><h2 class="card-title">Rejected, waiting for the practice to fix it</h2></div>
            @foreach ($rejected as $organization)
                <a class="block border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0 sm:px-5" href="{{ route('admin.approvals.show', $organization) }}">
                    <span class="font-medium text-slate-900">{{ $organization->name }}</span>
                    <span class="block truncate text-slate-500">{{ $organization->rejection_reason }}</span>
                </a>
            @endforeach
        </section>
    @endif
</x-layouts.admin>
