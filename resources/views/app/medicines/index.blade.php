<x-layouts.app title="Medicines">
    <x-page-header title="Medicine catalogue" subtitle="Search every department. Favourites show first when you write a prescription.">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="search" name="q" value="{{ $search }}" placeholder="Name, for example amlo 5" aria-label="Search catalogue">
            <select class="input w-auto" name="system" data-auto-submit aria-label="Medical system">
                <option value="">All systems</option>
                @foreach (\App\Support\Specialties::systems() as $option)<option value="{{ $option }}" @selected($system === $option)>{{ $option }}</option>@endforeach
            </select>
            <select class="input w-auto" name="specialty" data-auto-submit aria-label="Department">
                <option value="">All departments</option>
                @foreach (\App\Support\Specialties::bySystem() as $group => $options)
                    <optgroup label="{{ $group }}">@foreach ($options as $option)<option value="{{ $option }}" @selected($specialty === $option)>{{ $option }}</option>@endforeach</optgroup>
                @endforeach
            </select>
        </form>
    </x-page-header>
    @if ($results !== null)
        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">Catalogue results</h3></div>
            @forelse ($results as $medicine)
                <form method="POST" action="{{ route('app.medicines.favourite') }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5">
                    @csrf
                    <input type="hidden" name="medicine_id" value="{{ $medicine->id }}">
                    <span class="min-w-0">{{ $medicine->displayName() }} · {{ $medicine->form }}@if ($medicine->specialty)<span class="block text-xs text-slate-500">{{ $medicine->system === $medicine->specialty ? $medicine->system : $medicine->system.' · '.$medicine->specialty }}</span>@endif</span>
                    <button class="btn btn-sm btn-secondary">Add</button>
                </form>
            @empty
                <x-empty title="Not in the catalogue" text="You can request it below, and you can still type any medicine into a prescription." />
            @endforelse
            @if ($results->hasPages())<div class="p-4">{{ $results->links() }}</div>@endif
        </section>
    @endif
    <h2 class="mb-2 font-semibold text-slate-900">Your favourites</h2>
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
