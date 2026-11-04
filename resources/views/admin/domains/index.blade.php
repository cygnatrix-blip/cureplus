<x-layouts.admin title="Custom domains">
    <x-page-header title="Custom domains" :subtitle="$parkedCount.' of '.$parkedLimit.' parked domains used'" />
    @if ($parkedCount >= $parkedLimit - 10)
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">Close to the hosting limit on parked domains. See the VPS guide for automatic certificates.</p>
    @endif
    <section class="card">
        @if ($domains->isEmpty())
            <x-empty title="No domains yet" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Domain</th><th>Organization</th><th>Status</th><th>SSL</th><th>DNS checked</th><th></th></tr></thead>
                <tbody>
                    @foreach ($domains as $domain)
                        <tr>
                            <td class="font-medium">{{ $domain->hostname }}<div class="text-xs font-normal text-slate-500">{{ $domain->type }}</div></td>
                            <td>{{ $domain->organization?->name }}</td>
                            <td><span @class(['badge', 'badge-green' => $domain->status === 'active', 'badge-amber' => in_array($domain->status, ['awaiting_parking', 'parked'], true), 'badge-red' => $domain->status === 'dns_broken', 'badge-slate' => $domain->status === 'pending_dns'])>{{ str_replace('_', ' ', $domain->status) }}</span></td>
                            <td>{{ $domain->ssl_status ?? '–' }}</td>
                            <td class="whitespace-nowrap">{{ $domain->dns_checked_at?->format('d M H:i') }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    <form method="POST" action="{{ route('admin.domains.check', $domain->id) }}">@csrf<button class="btn btn-sm btn-secondary">Check DNS</button></form>
                                    @if ($domain->status === 'awaiting_parking')
                                        <form method="POST" action="{{ route('admin.domains.park', $domain->id) }}" data-confirm="Have you added {{ $domain->hostname }} as a parked domain in hPanel?">@csrf<button class="btn btn-sm btn-primary">Mark as parked</button></form>
                                    @elseif ($domain->status === 'parked')
                                        <form method="POST" action="{{ route('admin.domains.retry', $domain->id) }}">@csrf<button class="btn btn-sm btn-primary">Retry HTTPS</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $domains->links() }}</div>
</x-layouts.admin>
