<x-layouts.admin title="Backups">
    <x-page-header title="Backups" subtitle="Encrypted nightly, kept for 30 days.">
        <form method="POST" action="{{ route('admin.backups.store') }}">@csrf<button class="btn btn-primary">Back up now</button></form>
    </x-page-header>
    <section class="card">
        @if ($runs->isEmpty())
            <x-empty title="No backups yet" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Status</th><th>Size</th><th>Tables</th><th>Off-site</th><th></th></tr></thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td class="whitespace-nowrap">{{ $run->created_at?->format('d M Y H:i') }}<div class="font-mono text-xs text-slate-500">{{ $run->filename }}</div></td>
                            <td><span @class(['badge', 'badge-green' => $run->status === 'completed', 'badge-red' => $run->status === 'failed', 'badge-slate' => ! in_array($run->status, ['completed', 'failed'], true)])>{{ ucfirst($run->status) }}</span> {{ $run->error }}</td>
                            <td>{{ round($run->size_bytes / 1048576, 2) }} MB</td>
                            <td>{{ $run->tables_count }}</td>
                            <td>{{ $run->copied_offsite ? 'Yes' : 'No' }}</td>
                            <td class="text-right">
                                @if ($run->status === 'completed')
                                    <div class="flex justify-end gap-1">
                                        <form method="POST" action="{{ route('admin.backups.verify', $run) }}">@csrf<button class="btn btn-sm btn-secondary">Verify</button></form>
                                        <a class="btn btn-sm btn-secondary" href="{{ route('admin.backups.download', $run) }}">Download</a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $runs->links() }}</div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Restore test log</h2></div>
            @forelse ($tests as $test)
                <div class="border-b border-slate-100 px-4 py-2 text-sm last:border-b-0"><span @class(['badge', 'badge-green' => $test->result === 'passed', 'badge-red' => $test->result !== 'passed'])>{{ ucfirst($test->result) }}</span> {{ $test->created_at?->format('d M Y') }} · {{ $test->notes }}</div>
            @empty
                <x-empty title="No restore tests yet" text="Test a restore every month." />
            @endforelse
        </section>
        <form method="POST" action="{{ route('admin.backups.restore-tests') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="font-semibold text-slate-900">Log a full restore test</h2>
            <p class="text-sm text-slate-600">Verify proves a file is readable. Once a month, also restore one to a spare database (see the deployment guide) and record it here.</p>
            <div><label class="label" for="result">Result</label><select class="input" id="result" name="result"><option value="passed">Passed</option><option value="failed">Failed</option></select></div>
            <x-field name="notes" label="Notes" required />
            <button class="btn btn-secondary">Log test</button>
        </form>
    </div>
</x-layouts.admin>
