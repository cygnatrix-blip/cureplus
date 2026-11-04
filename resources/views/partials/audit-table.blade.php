<div class="card">
    @if ($logs->isEmpty())
        <x-empty title="No activity found" />
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>IP</th></tr></thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $log->actor_name ?? 'System' }} @if ($log->impersonator_id)<span class="badge badge-amber">Impersonated</span>@endif</td>
                        <td class="font-mono text-xs">{{ $log->action }}</td>
                        <td>{{ $log->subject_type }} {{ $log->subject_id }}</td>
                        <td>{{ $log->ip_address }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>
<div class="mt-4">{{ $logs->links() }}</div>
