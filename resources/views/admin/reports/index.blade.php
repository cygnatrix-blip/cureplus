<x-layouts.admin title="Reports">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Monthly recurring revenue" :value="$money::format($mrr)" />
        <x-stat label="Active organizations" :value="$activeOrganizations" />
        <x-stat label="Churn, last 30 days" :value="$churn['rate'].'%'" />
        <x-stat label="Cancelled, last 30 days" :value="$churn['cancelled']" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Last 12 months</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Month</th><th class="text-right">Signups</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    @foreach ($months as $row)<tr><td>{{ $row['month'] }}</td><td class="text-right">{{ $row['signups'] }}</td><td class="text-right">{{ $money::format($row['revenue']) }}</td></tr>@endforeach
                </tbody>
            </table></div>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="card-title">Organizations using each feature, last 30 days</h2></div>
            @foreach ($adoption as $feature => $count)
                <div class="flex justify-between border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5"><span>{{ $feature }}</span><span class="font-medium">{{ $count }}</span></div>
            @endforeach
        </section>
    </div>
</x-layouts.admin>
