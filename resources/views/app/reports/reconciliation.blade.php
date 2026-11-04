<x-layouts.app title="Razorpay reconciliation">
    <x-page-header title="Razorpay reconciliation">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="date" name="from" value="{{ $from->toDateString() }}" data-auto-submit aria-label="From">
            <input class="input w-auto" type="date" name="to" value="{{ $to->toDateString() }}" data-auto-submit aria-label="To">
        </form>
    </x-page-header>
    @if ($error)
        <div class="card"><x-empty :title="$error" /></div>
    @else
        <div class="grid gap-3 sm:grid-cols-4">
            <x-stat label="Matched" :value="$result['matched']" />
            <x-stat label="Only at Razorpay" :value="count($result['missing_locally'])" :tone="$result['missing_locally'] ? 'bad' : null" />
            <x-stat label="Only in bills" :value="count($result['missing_at_gateway'])" :tone="$result['missing_at_gateway'] ? 'bad' : null" />
            <x-stat label="Amount differs" :value="count($result['amount_mismatch'])" :tone="$result['amount_mismatch'] ? 'bad' : null" />
        </div>
        <section class="card mt-4">
            @php($problems = count($result['missing_locally']) + count($result['missing_at_gateway']) + count($result['amount_mismatch']))
            @if ($problems === 0)
                <x-empty title="Everything matches" />
            @endif
            @foreach ($result['missing_locally'] as $row)
                <div class="border-b border-slate-100 px-4 py-2 text-sm last:border-b-0">Only at Razorpay: <span class="font-mono">{{ $row['id'] }}</span> · {{ $money::format($row['amount_paise']) }}</div>
            @endforeach
            @foreach ($result['missing_at_gateway'] as $payment)
                <div class="border-b border-slate-100 px-4 py-2 text-sm last:border-b-0">Only in bills: {{ $payment->invoice?->number }} · {{ $money::format($payment->amount_paise) }}</div>
            @endforeach
            @foreach ($result['amount_mismatch'] as $row)
                <div class="border-b border-slate-100 px-4 py-2 text-sm last:border-b-0">Differs: <span class="font-mono">{{ $row['id'] }}</span> · ours {{ $money::format($row['local']) }}, Razorpay {{ $money::format($row['gateway']) }}</div>
            @endforeach
        </section>
    @endif
</x-layouts.app>
