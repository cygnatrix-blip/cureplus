@php($noun = $tenant->organization()->type->locationNoun())
<x-layouts.app :title="($location->exists ? 'Edit ' : 'Add ').strtolower($noun)">
    <form method="POST" action="{{ $location->exists ? route('app.locations.update', $location) : route('app.locations.store') }}" class="card card-body mx-auto max-w-2xl space-y-4">
        @csrf
        @if ($location->exists) @method('PUT') @endif
        <x-field name="name" label="Name" :value="$location->name" required />
        <x-field name="address_line" label="Address" :value="$location->address_line" />
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="city" label="City" :value="$location->city" />
            <x-field name="state" label="State" :value="$location->state" />
            <x-field name="pincode" label="PIN code" :value="$location->pincode" inputmode="numeric" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="phone" label="Phone" type="tel" :value="$location->phone" />
            <x-field name="email" label="Email" type="email" :value="$location->email" />
        </div>
        <x-field name="timings[text]" label="Opening hours" :value="$location->timings['text'] ?? null" placeholder="Mon to Sat, 9 am to 8 pm" hint="Shown to patients. Bookable slots come from the doctor calendar." />
        <fieldset class="space-y-3 rounded-lg border border-slate-200 p-3">
            <legend class="px-1 text-sm font-medium">Prescription pad</legend>
            <x-field name="letterhead[title]" label="Letterhead title" :value="$location->letterhead['title'] ?? null" />
            <x-field name="letterhead[subtitle]" label="Letterhead subtitle" :value="$location->letterhead['subtitle'] ?? null" />
            <x-field name="rx_pad[footer]" label="Footer note" :value="$location->rx_pad['footer'] ?? null" />
        </fieldset>
        @if ($location->display_token)<p class="text-sm text-slate-600">TV queue display: <a class="text-brand-700 underline break-all" href="{{ route('display.show', $location->display_token) }}">{{ route('display.show', $location->display_token) }}</a></p>@endif
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accepts_online_booking" value="1" @checked(old('accepts_online_booking', $location->exists ? $location->accepts_online_booking : true))> Patients can book this location online</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active))> Taking patients</label>
        <div class="flex justify-end gap-2"><a class="btn btn-ghost" href="{{ route('app.locations.index') }}">Cancel</a><button class="btn btn-primary">Save</button></div>
    </form>
    @if ($location->exists)
        <form method="POST" action="{{ route('app.locations.destroy', $location) }}" class="mx-auto mt-4 max-w-2xl text-right" data-confirm="Remove {{ $location->name }}?">
            @csrf @method('DELETE')
            <button class="btn btn-danger">Remove {{ strtolower($noun) }}</button>
        </form>
    @endif
</x-layouts.app>
