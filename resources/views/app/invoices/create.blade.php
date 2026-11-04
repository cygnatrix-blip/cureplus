<x-layouts.app title="New bill">
    <div class="mx-auto max-w-3xl space-y-4">
        @unless ($patient)
            <section class="card card-body" x-data="patientPicker" data-url="{{ route('app.patients.search') }}" data-target="{{ route('app.invoices.create') }}">
                <h2 class="font-semibold text-slate-900">Patient</h2>
                <input class="input mt-2" type="search" placeholder="Search by name, mobile or ID" aria-label="Search patient" @input="search" autofocus>
                <ul class="mt-2 divide-y divide-slate-100">
                    <template x-for="result in results" :key="result.id">
                        <li><a class="block px-2 py-3 text-sm hover:bg-slate-50" :href="result.url" x-text="result.label"></a></li>
                    </template>
                </ul>
            </section>
        @else
            <form method="POST" action="{{ route('app.invoices.store') }}" class="card card-body space-y-4" x-data="billEditor"
                data-fee-url="{{ route('app.invoices.fee') }}" data-patient="{{ $patient->id }}" @submit="serialize">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <input type="hidden" name="appointment_id" value="{{ $appointment?->id }}">
                <input type="hidden" name="items_json" x-ref="itemsJson">
                <p class="font-semibold text-slate-900">{{ $patient->name }} <span class="font-normal text-slate-500">{{ $patient->mobile }} · {{ $patient->patient_code }}</span></p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="doctor_user_id">Doctor</label>
                        <select class="input" id="doctor_user_id" name="doctor_user_id" x-ref="doctor" @change="loadFee">
                            <option value="">No doctor</option>
                            @foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(($appointment?->doctor_user_id ?? null) === $doctor->id)>{{ $doctor->name }}</option>@endforeach
                        </select>
                        @error('doctor_user_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="visit_type">Visit type</label>
                        <select class="input" id="visit_type" x-ref="visitType" @change="loadFee">
                            @foreach (\App\Enums\VisitType::cases() as $case)<option value="{{ $case->value }}" @selected($appointment?->visit_type === $case)>{{ $case->label() }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <p class="text-sm text-brand-700" x-show="hasFeeNote" x-text="feeNote" x-cloak></p>
                <div class="space-y-2">
                    <template x-for="item in items" :key="item.key">
                        <div class="grid grid-cols-6 items-center gap-2">
                            <input class="input col-span-3" aria-label="Description" :value="item.description" :data-key="item.key" data-field="description" @input="update">
                            <input class="input" type="number" min="1" aria-label="Quantity" :value="item.quantity" :data-key="item.key" data-field="quantity" @input="update">
                            <input class="input" inputmode="decimal" aria-label="Price in rupees" :value="item.unit" :data-key="item.key" data-field="unit" @input="update">
                            <button type="button" class="btn btn-sm btn-ghost" aria-label="Remove" :data-key="item.key" @click="remove">&times;</button>
                        </div>
                    </template>
                </div>
                @error('items_json')<p class="field-error">{{ $message }}</p>@enderror
                @if ($serviceItems->isNotEmpty())
                    <select class="input" aria-label="Add an item" @change="addService">
                        <option value="">Add an item…</option>
                        @foreach ($serviceItems as $item)
                            <option value="{{ $item->id }}" data-name="{{ $item->name }}" data-price="{{ \App\Support\Money::decimal($item->price_paise) }}">{{ $item->name }} · {{ \App\Support\Money::format($item->price_paise) }}{{ $item->is_taxable ? ' + '.($item->gst_rate_bp / 100).'% GST' : '' }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($canDiscount)
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="discount" label="Discount (₹)" inputmode="decimal" />
                        <x-field name="discount_reason" label="Reason for discount" />
                    </div>
                @endif
                <p class="text-right text-lg font-semibold">Before tax: ₹<span x-text="subtotal"></span></p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label" for="pay_mode">Paid by</label>
                        <select class="input" id="pay_mode" name="pay_mode">
                            <option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="">Not paid yet</option>
                        </select>
                    </div>
                    <x-field name="pay_amount" label="Amount received (₹)" inputmode="decimal" />
                    <x-field name="pay_reference" label="Reference (optional)" />
                </div>
                <div class="text-right"><button class="btn btn-primary">Save bill</button></div>
            </form>
        @endunless
    </div>
</x-layouts.app>
