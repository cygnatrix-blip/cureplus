@php($money = \App\Support\Money::class)
<x-layouts.app title="Reports">
    <x-page-header title="Daily cash report" :subtitle="$date->format('d M Y')">
        <form method="GET"><input class="input w-auto" type="date" name="date" value="{{ $date->toDateString() }}" data-auto-submit aria-label="Date"></form>
        <button type="button" class="btn btn-secondary print:hidden" data-print>Print</button>
    </x-page-header>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach (\App\Models\Payment::MODES as $mode => $label)<x-stat :label="$label" :value="$money::format($cash['modes'][$mode] ?? 0)" />@endforeach
        <x-stat label="Net after refunds" :value="$money::format($cash['net'])" />
    </div>
    <section class="card mt-4">
        @if ($cash['rows'] === [])
            <x-empty title="No payments on this day" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Doctor</th>@foreach (\App\Models\Payment::MODES as $label)<th class="text-right">{{ $label }}</th>@endforeach<th class="text-right">Refunds</th><th class="text-right">Net</th></tr></thead>
                <tbody>
                    @foreach ($cash['rows'] as $row)
                        <tr>
                            <td class="font-medium">{{ $row['doctor'] }}</td>
                            @foreach (\App\Models\Payment::MODES as $mode => $label)<td class="text-right">{{ $money::format($row['modes'][$mode] ?? 0) }}</td>@endforeach
                            <td class="text-right">{{ $money::format($row['refunds']) }}</td>
                            <td class="text-right font-semibold">{{ $money::format($row['net']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <h2 class="text-lg font-semibold text-slate-900">Income and patients</h2>
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input class="input w-auto" type="date" name="from" value="{{ $from->toDateString() }}" data-auto-submit aria-label="From">
            <input class="input w-auto" type="date" name="to" value="{{ $to->toDateString() }}" data-auto-submit aria-label="To">
        </form>
    </div>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Exempt revenue" :value="$money::format($income['exempt'])" />
        <x-stat label="Taxable revenue" :value="$money::format($income['taxable'])" />
        <x-stat label="GST charged" :value="$money::format($income['tax'])" />
        <x-stat label="Collected, net of refunds" :value="$money::format($income['collected'] - $income['refunded'])" />
        <x-stat label="New patients" :value="$patients['new']" />
        <x-stat label="Patients seen" :value="$patients['seen']" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h3 class="card-title">Doctor-wise revenue</h3></div>
            @forelse ($doctors as $row)
                <div class="flex justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5"><span>{{ $row['doctor'] }} ({{ $row['bills'] }} bills)</span><span class="font-medium">{{ $money::format($row['collected']) }}</span></div>
            @empty
                <x-empty title="No bills in this period" />
            @endforelse
        </section>
        <section class="card">
            <div class="card-header"><h3 class="card-title">Top diagnoses</h3></div>
            @forelse ($diagnoses as $name => $count)
                <div class="flex justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5"><span>{{ ucfirst($name) }}</span><span class="font-medium">{{ $count }}</span></div>
            @empty
                <x-empty title="No diagnoses in this period" />
            @endforelse
        </section>
    </div>
    <section class="card card-body mt-4 print:hidden">
        <h3 class="font-semibold text-slate-900">Monthly income for your CA</h3>
        <form method="GET" class="mt-2 flex flex-wrap items-center gap-2">
            <input class="input w-auto" type="month" name="month" value="{{ $month }}" aria-label="Month">
            <button class="btn btn-secondary" formaction="{{ route('app.reports.monthly.xlsx') }}">Download Excel</button>
            <button class="btn btn-secondary" formaction="{{ route('app.reports.monthly.pdf') }}">Download PDF</button>
            <a class="btn btn-ghost" href="{{ route('app.reports.reconciliation') }}">Razorpay reconciliation</a>
        </form>
    </section>
</x-layouts.app>
