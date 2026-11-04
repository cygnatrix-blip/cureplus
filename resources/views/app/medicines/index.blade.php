<x-layouts.app title="Medicines">
    <x-page-header title="Favourite medicines" subtitle="Favourites show first when you write a prescription.">
        <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Search the catalogue" aria-label="Search catalogue"></form>
    </x-page-header>
    @if ($search !== '')
        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">Catalogue results</h3></div>
            @forelse ($results as $medicine)
                <form method="POST" action="{{ route('app.medicines.favourite') }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5">
                    @csrf
                    <input type="hidden" name="medicine_id" value="{{ $medicine->id }}">
                    <span>{{ $medicine->displayName() }} · {{ $medicine->generic_name }} · {{ $medicine->form }}</span>
                    <button class="btn btn-sm btn-secondary">Add</button>
                </form>
            @empty
                <x-empty title="Not in the catalogue" text="You can request it below." />
            @endforelse
        </section>
    @endif
    <section class="card">
        @forelse ($favourites as $favourite)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5">
                <span>{{ $favourite->medicine?->displayName() }}</span>
                <form method="POST" action="{{ route('app.medicines.unfavourite', $favourite) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Remove</button></form>
            </div>
        @empty
            <x-empty title="No favourites yet" />
        @endforelse
    </section>
    <form method="POST" action="{{ route('app.medicines.request') }}" class="card card-body mt-4 space-y-3">
        @csrf
        <h3 class="font-semibold text-slate-900">Request a medicine for the catalogue</h3>
        <div class="grid gap-3 sm:grid-cols-3">
            <x-field name="generic_name" label="Generic name" required />
            <x-field name="brand_name" label="Brand" />
            <x-field name="strength" label="Strength" />
            <x-field name="form" label="Form" placeholder="Tablet, syrup…" />
            <x-field name="manufacturer" label="Manufacturer" />
        </div>
        <button class="btn btn-secondary">Send request</button>
        @if ($requests->isNotEmpty())
            <ul class="text-sm text-slate-600">
                @foreach ($requests as $item)<li>{{ $item->generic_name }} {{ $item->strength }}: {{ ucfirst($item->status) }} @if ($item->rejection_reason)({{ $item->rejection_reason }})@endif</li>@endforeach
            </ul>
        @endif
    </form>
</x-layouts.app>
