@php($money = \App\Support\Money::class)
<x-layouts.app title="Billing">
    <x-page-header title="Bills">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="search" name="q" value="{{ $search }}" placeholder="Number or patient" aria-label="Search bills">
            <input class="input w-auto" type="date" name="date" value="{{ $date }}" data-auto-submit aria-label="Date">
            <select class="input w-auto" name="status" data-auto-submit aria-label="Status">
                <option value="">All statuses</option>
                @foreach (['unpaid' => 'Unpaid', 'partial' => 'Part paid', 'paid' => 'Paid', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <a class="btn btn-secondary" href="{{ route('app.invoices.export', request()->query()) }}">Export CSV</a>
        <a class="btn btn-secondary" href="{{ route('app.dues.index') }}">Dues</a>
        @can('billing.manage')<a class="btn btn-primary" href="{{ route('app.invoices.create') }}" data-shortcut="r">New bill</a>@endcan
    </x-page-header>
    <div class="card">
        @if ($invoices->isEmpty())
            <x-empty title="No bills found" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Number</th><th>Date</th><th>Patient</th><th>Doctor</th><th class="text-right">Total</th><th class="text-right">Due</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="font-mono text-xs whitespace-nowrap">{{ $invoice->number }}</td>
                            <td class="whitespace-nowrap">{{ $invoice->issued_at->format('d M Y') }}</td>
                            <td>{{ $invoice->patient?->name }}</td>
                            <td>{{ $invoice->doctor?->name }}</td>
                            <td class="text-right">{{ $money::format($invoice->total_paise) }}</td>
                            <td class="text-right">{{ $money::format($invoice->duePaise()) }}</td>
                            <td><span @class(['badge', 'badge-green' => $invoice->status === 'paid', 'badge-amber' => in_array($invoice->status, ['partial', 'unpaid'], true), 'badge-red' => in_array($invoice->status, ['cancelled', 'refunded'], true)])>{{ ucfirst($invoice->status) }}</span></td>
                            <td class="text-right"><a class="btn btn-sm btn-secondary" href="{{ route('app.invoices.show', $invoice) }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
