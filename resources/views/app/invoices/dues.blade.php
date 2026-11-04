@php($money = \App\Support\Money::class)
<x-layouts.app title="Dues">
    <x-page-header title="Dues" :subtitle="'Total due: '.$totalDue" />
    <div class="card">
        @forelse ($invoices as $invoice)
            <a class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0 sm:px-5" href="{{ route('app.invoices.show', $invoice) }}">
                <span>{{ $invoice->patient?->name }} · {{ $invoice->patient?->mobile }} · {{ $invoice->number }}</span>
                <span class="font-medium">{{ $money::format($invoice->duePaise()) }}</span>
            </a>
        @empty
            <x-empty title="Nothing is due" />
        @endforelse
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
