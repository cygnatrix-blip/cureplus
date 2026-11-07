<x-layouts.admin title="File storage">
    <x-page-header title="File storage" subtitle="Practices that copy patient files to their own Google Drive." />
    <p class="mb-4 text-sm text-slate-600">Server copies stay until you remove them. Ask the practice first: if they want CurePlus to keep a copy, that is charged separately; otherwise record their agreement and remove the server copies. Each file is checked in Google Drive before its server copy is deleted.</p>
    @forelse ($rows as $row)
        @php($organization = $row['organization'])
        @php($summary = $row['summary'])
        <section class="card mb-4">
            <div class="card-header flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">{{ $organization->name }}</h2>
                <span @class(['badge', 'badge-amber' => $organization->drive_server_copy === null, 'badge-green' => $organization->drive_server_copy !== null])>{{ $organization->drive_server_copy === null ? 'Not asked yet' : \App\Services\Storage\DriveFiles::CHOICES[$organization->drive_server_copy] }}</span>
            </div>
            <div class="card-body space-y-3 text-sm">
                <ul class="space-y-1">
                    @foreach ($row['accounts'] as $account)
                        <li><span class="break-all">{{ $account->google_email }}</span> · {{ $account->statusLabel() }} · {{ $account->freeBytes() === null ? 'no limit' : \App\Models\DriveAccount::readableSize($account->freeBytes()).' free' }}@if ($account->last_error) · <span class="text-amber-700">{{ $account->last_error }}</span>@endif</li>
                    @endforeach
                </ul>
                <p>{{ $summary['synced'] }} files in Drive · {{ $summary['waiting'] }} waiting · <strong>{{ \App\Models\DriveAccount::readableSize($summary['server_bytes']) }}</strong> on the server that is also in Drive · {{ $summary['only_in_drive'] }} only in Drive</p>
                @if ($organization->drive_server_copy_decided_at)
                    <p class="text-slate-600">Recorded {{ $organization->drive_server_copy_decided_at->format('d M Y') }}: {{ $organization->drive_server_copy_note }}</p>
                @endif
                <form method="POST" action="{{ route('admin.drive.decide', $organization) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="label" for="choice-{{ $organization->id }}">Practice's choice</label>
                        <select class="input" id="choice-{{ $organization->id }}" name="choice" required>
                            @foreach (\App\Services\Storage\DriveFiles::CHOICES as $value => $label)<option value="{{ $value }}" @selected($organization->drive_server_copy === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <x-field name="note" label="How they agreed (who, when, ticket or email)" required />
                    <button class="btn btn-secondary">Save choice</button>
                </form>
                <div class="flex flex-wrap gap-2">
                    @if ($organization->drive_server_copy === \App\Services\Storage\DriveFiles::REMOVE && $summary['server_bytes'] > 0)
                        <form method="POST" action="{{ route('admin.drive.remove', $organization) }}" data-confirm="Remove the server copies of {{ $organization->name }}'s files? Their Google Drive will hold the only copy.">@csrf<button class="btn btn-danger">Remove server copies</button></form>
                    @endif
                    @if ($summary['only_in_drive'] > 0)
                        <form method="POST" action="{{ route('admin.drive.restore', $organization) }}" data-confirm="Copy {{ $summary['only_in_drive'] }} files back from Google Drive to the server?">@csrf<button class="btn btn-secondary">Bring files back to the server</button></form>
                    @endif
                </div>
            </div>
        </section>
    @empty
        <section class="card"><x-empty title="No practice has connected Google Drive" /></section>
    @endforelse
</x-layouts.admin>
