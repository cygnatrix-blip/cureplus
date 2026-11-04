<x-layouts.patient :title="__('Records')">
    <h1 class="mb-4 text-xl font-bold text-slate-900">{{ __('Records') }}</h1>
    <div class="grid gap-5 lg:grid-cols-2">
        <section id="prescriptions" class="card scroll-mt-20">
            <div class="card-header"><h2 class="card-title flex items-center gap-2"><x-icon name="document" class="text-brand-600" />{{ __('Prescriptions') }}</h2></div>
            @forelse ($prescriptions as $prescription)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                    <span class="min-w-0"><span class="block truncate font-medium text-slate-900">{{ $prescription->doctor_name }}</span><span class="block truncate text-xs text-slate-500">{{ $prescription->issued_at?->format('d M Y') }} · {{ $prescription->organization?->name }}</span></span>
                    <a class="btn btn-sm btn-secondary" href="{{ route('patient.prescriptions.pdf', $prescription->id) }}"><x-icon name="download" class="h-4 w-4" />{{ __('Download') }}</a>
                </div>
            @empty
                <x-empty :title="__('Nothing here yet.')" />
            @endforelse
        </section>
        <section id="bills" class="card scroll-mt-20">
            <div class="card-header"><h2 class="card-title flex items-center gap-2"><x-icon name="card" class="text-brand-600" />{{ __('Bills and receipts') }}</h2></div>
            @forelse ($invoices as $invoice)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                    <span class="min-w-0"><span class="block font-medium text-slate-900">{{ \App\Support\Money::format($invoice->total_paise) }}</span><span class="block truncate text-xs text-slate-500">{{ $invoice->issued_at->format('d M Y') }} · {{ $invoice->organization?->name }}</span></span>
                    <span class="flex gap-1">
                        @if ($invoice->duePaise() > 0 && in_array($invoice->organization_id, $payable))
                            <form method="POST" action="{{ route('patient.invoices.pay', $invoice->id) }}">@csrf<button class="btn btn-sm btn-primary">{{ __('Pay online') }}</button></form>
                        @endif
                        <a class="btn btn-sm btn-secondary" href="{{ route('patient.invoices.pdf', $invoice->id) }}"><x-icon name="download" class="h-4 w-4" />{{ __('Download') }}</a>
                    </span>
                </div>
            @empty
                <x-empty :title="__('Nothing here yet.')" />
            @endforelse
        </section>
        <section id="reports" class="card scroll-mt-20 lg:col-span-2">
            <div class="card-header"><h2 class="card-title flex items-center gap-2"><x-icon name="flask" class="text-brand-600" />{{ __('Lab reports') }}</h2></div>
            @forelse ($reports as $report)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                    <span class="min-w-0 truncate">{{ $report->original_name }} · {{ $report->organization?->name }}</span>
                    <a class="btn btn-sm btn-secondary" href="{{ route('patient.reports.show', $report->id) }}"><x-icon name="download" class="h-4 w-4" />{{ __('Download') }}</a>
                </div>
            @empty
                <x-empty :title="__('Nothing here yet.')" />
            @endforelse
        </section>
    </div>
</x-layouts.patient>
