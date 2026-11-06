@php($money = \App\Support\Money::class)
<x-layouts.app title="Fees and services">
    <form method="POST" action="{{ route('app.billing-settings.fees') }}" class="card">
        @csrf
        <div class="card-header"><h2 class="card-title">Consultation fees (₹)</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Doctor</th>@foreach ($visitTypes as $type)<th>{{ $type->label() }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ([null, ...$doctors] as $doctor)
                    @php($key = $doctor?->id ?? 'default')
                    <tr>
                        <td class="font-medium whitespace-nowrap">{{ $doctor?->name ?? 'Default for everyone' }}</td>
                        @foreach ($visitTypes as $type)
                            @php($fee = $fees->first(fn ($row) => (int) $row->doctor_user_id === (int) $doctor?->id && $row->visit_type === $type->value))
                            <td><input class="input w-24" name="fees[{{ $key }}][{{ $type->value }}]" inputmode="decimal" value="{{ $fee ? $money::decimal($fee->amount_paise) : '' }}" aria-label="{{ $doctor?->name ?? 'Default' }} {{ $type->label() }} fee"></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="card-body grid gap-3 border-t border-slate-100 sm:grid-cols-2">
            <x-field name="follow_up_window_days" label="Follow-up window (days)" type="number" min="0" max="365" :value="$settings['follow_up_window_days'] ?? 0" hint="0 turns this off." />
            <x-field name="follow_up_discount_percent" label="Follow-up discount in that window (%)" type="number" min="0" max="100" :value="$settings['follow_up_discount_percent'] ?? 100" hint="100 makes the follow-up free." />
        </div>
        <div class="px-4 pb-4 text-right"><button class="btn btn-primary">Save fees</button></div>
    </form>
    <section class="card mt-4">
        <div class="card-header"><h2 class="card-title">Services and items</h2></div>
        @forelse ($serviceItems as $item)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0 sm:px-5">
                <span>{{ $item->name }} · {{ $money::format($item->price_paise) }} @if ($item->is_taxable)<span class="badge badge-amber">{{ $item->gst_rate_bp / 100 }}% GST</span>@else<span class="badge badge-slate">Exempt</span>@endif</span>
                <form method="POST" action="{{ route('app.billing-settings.items.destroy', $item) }}" data-confirm="Remove {{ $item->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Remove</button></form>
            </div>
        @empty
            <x-empty title="No items yet" text="Add procedures or pharmacy items to bill them." />
        @endforelse
    </section>
    <div class="mt-4">{{ $serviceItems->links() }}</div>
    <form method="POST" action="{{ route('app.billing-settings.items.store') }}" class="card card-body mt-4 space-y-3">
        @csrf
        <h3 class="font-semibold text-slate-900">Add an item</h3>
        <div class="grid gap-3 sm:grid-cols-4">
            <x-field name="name" label="Name" required />
            <x-field name="price" label="Price (₹)" inputmode="decimal" required />
            <x-field name="gst_rate" label="GST %" inputmode="decimal" />
            <x-field name="sac_hsn_code" label="SAC/HSN code" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_taxable" value="1"> GST applies to this item</label>
        @error('is_taxable')<p class="field-error">{{ $message }}</p>@enderror
        <p class="text-xs text-slate-500">Consultations and treatment are exempt from GST. Tick this only for taxable items, such as an instrument or a cosmetic procedure, and only if your practice is GST-registered: your GSTIN must be saved in Organization settings. Ask your CA for the rate and HSN/SAC code.</p>
        <button class="btn btn-secondary">Add item</button>
    </form>
</x-layouts.app>
