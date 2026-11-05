<x-layouts.admin title="Pending approvals">
    <x-page-header title="Pending approvals" subtitle="Practices and doctors. Oldest first." />
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

    <section class="card mt-4">
        <div class="card-header"><h2 class="card-title">Doctors waiting to be checked</h2></div>
        <p class="px-4 pt-3 text-sm text-slate-600 sm:px-5">Patients cannot book these doctors yet. Check each number on the <a class="text-brand-700 underline" href="https://www.nmc.org.in/information-desk/indian-medical-register/" target="_blank" rel="noopener noreferrer">Indian Medical Register</a>, which is outside CurePlus.</p>
        @forelse ($doctors as $row)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 text-sm last:border-b-0 sm:px-5">
                <div class="min-w-0">
                    <p class="font-medium text-slate-900">{{ $row['profile']->user?->name }}</p>
                    <p class="text-slate-500">{{ $row['clinics'] }}</p>
                    @if (filled($row['profile']->registration_number))<p class="mt-1">{{ $row['profile']->registration_number }} · {{ $row['profile']->registration_council }}</p>@endif
                    @if ($row['profile']->specialty)<p class="text-slate-500">{{ $row['profile']->specialty }}</p>@endif
                    @if ($certificate = $row['profile']->certificate())<a class="text-brand-700 underline" target="_blank" rel="noopener" href="{{ route('admin.approvals.document', [$certificate->organization_id, $certificate->id]) }}">View degree or membership certificate</a>@endif
                    @if ($row['profile']->qualification)<p class="text-slate-500">{{ $row['profile']->qualification }}</p>@endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.doctors.verify', $row['profile']) }}">@csrf<button class="btn btn-sm btn-primary" name="status" value="verified">Verify</button></form>
                    <form method="POST" action="{{ route('admin.doctors.verify', $row['profile']) }}" class="flex gap-1">@csrf<input class="input min-h-9 w-52 py-1 text-sm" name="reason" placeholder="Reason, if rejecting" aria-label="Reason for rejecting {{ $row['profile']->user?->name }}" required><button class="btn btn-sm btn-secondary" name="status" value="rejected">Reject</button></form>
                </div>
            </div>
        @empty
            <x-empty title="No doctors are waiting" />
        @endforelse
    </section>

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
