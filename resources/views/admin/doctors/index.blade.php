<x-layouts.admin title="Doctors">
    <x-page-header title="Doctor directory">
        <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Name" aria-label="Search doctors"></form>
    </x-page-header>
    <div class="card">
        @if ($doctors->isEmpty())
            <x-empty title="No doctors found" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th>Qualification</th><th>Registration</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($doctors as $row)
                        @php($profile = $profiles->get($row->user_id))
                        <tr>
                            <td class="font-medium text-slate-900">{{ $row->user?->name }}</td>
                            <td>{{ $profile?->qualification }}</td>
                            <td>{{ $profile?->registration_number }} {{ $profile?->registration_council }}</td>
                            <td><span @class(['badge', 'badge-green' => $profile?->verification_status === 'verified', 'badge-red' => $profile?->verification_status === 'rejected', 'badge-slate' => $profile?->verification_status !== 'verified' && $profile?->verification_status !== 'rejected'])>{{ ucfirst($profile?->verification_status ?? 'No profile') }}</span></td>
                            <td class="text-right">
                                @if ($profile)
                                    @can('platform.approvals.manage')
                                        <form method="POST" action="{{ route('admin.doctors.verify', $profile) }}" class="flex justify-end gap-1">@csrf<button class="btn btn-sm btn-primary" name="status" value="verified">Verify</button></form>
                                        <form method="POST" action="{{ route('admin.doctors.verify', $profile) }}" class="mt-1 flex justify-end gap-1">@csrf<input class="input min-h-9 w-44 py-1 text-sm" name="reason" placeholder="Reason" aria-label="Reason for rejecting" required><button class="btn btn-sm btn-secondary" name="status" value="rejected">Reject</button></form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $doctors->links() }}</div>
</x-layouts.admin>
