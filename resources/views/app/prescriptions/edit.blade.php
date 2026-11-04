<x-layouts.app :title="'Prescription: '.$visit->patient->name">
    <x-page-header :title="'Prescription for '.$visit->patient->name" :subtitle="$visit->patient->allergies ? 'Allergies: '.$visit->patient->allergies : null">
        <a class="btn btn-secondary" href="{{ route('app.prescriptions.edit', ['visit' => $visit, 'copy_last' => 1]) }}">Copy last prescription</a>
        <a class="btn btn-ghost" href="{{ route('app.visits.show', $visit) }}">Back</a>
    </x-page-header>
    <form method="POST" action="{{ route('app.prescriptions.update', $visit) }}" class="space-y-4" x-data="rxEditor"
        data-items="{{ json_encode($items) }}" data-search-url="{{ route('app.medicines.search') }}" @submit="serialize">
        @csrf @method('PUT')
        <input type="hidden" name="items_json" x-ref="itemsJson">
        <section class="card card-body">
            <label class="label" for="medicine-search">Add medicine</label>
            <input class="input" id="medicine-search" type="search" placeholder="Type a brand or generic name" autocomplete="off" x-ref="search" @input="search" @keydown.enter.prevent="addTyped" autofocus>
            <ul class="mt-1 divide-y divide-slate-100" x-show="hasResults" x-cloak>
                <template x-for="result in results" :key="result.id">
                    <li><button type="button" class="block w-full px-2 py-3 text-left text-sm hover:bg-slate-50" :data-id="result.id" @click="pick"><span x-text="result.label"></span></button></li>
                </template>
            </ul>
            @error('items_json')<p class="field-error">{{ $message }}</p>@enderror
            <datalist id="dosage-shortcuts">
                @foreach (['1-0-1', '1-0-0', '0-0-1', '1-1-1', '0-1-0', '1-1-1-1', 'SOS'] as $dosage)<option value="{{ $dosage }}"></option>@endforeach
            </datalist>
            <div class="mt-3 space-y-3">
                <template x-for="item in items" :key="item.key">
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-slate-900" x-text="item.medicine_name"></p>
                            <button type="button" class="btn btn-sm btn-ghost" :data-key="item.key" @click="remove">Remove</button>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <input class="input" list="dosage-shortcuts" placeholder="1-0-1" aria-label="Dose" :value="item.dosage" :data-key="item.key" data-field="dosage" @input="update">
                            <select class="input" aria-label="When" :value="item.timing" :data-key="item.key" data-field="timing" @change="update">
                                <option value="">When</option>
                                <option value="before_food">Before food</option>
                                <option value="after_food">After food</option>
                                <option value="with_food">With food</option>
                                <option value="empty_stomach">Empty stomach</option>
                                <option value="bedtime">Bedtime</option>
                            </select>
                            <input class="input" type="number" min="1" max="365" placeholder="Days" aria-label="Days" :value="item.duration_days" :data-key="item.key" data-field="duration_days" @input="update">
                            <input class="input" placeholder="Notes" aria-label="Notes" :value="item.notes" :data-key="item.key" data-field="notes" @input="update">
                        </div>
                    </div>
                </template>
                <p class="text-sm text-slate-500" x-show="isEmpty">No medicines added yet.</p>
            </div>
        </section>
        <section class="card card-body grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="lab_advice">Tests advised</label>
                <textarea class="input" id="lab_advice" name="lab_advice" rows="3">{{ old('lab_advice', $prescription?->lab_advice) }}</textarea>
            </div>
            <div>
                <label class="label" for="patient_instructions">Instructions for the patient</label>
                <textarea class="input" id="patient_instructions" name="patient_instructions" rows="3">{{ old('patient_instructions', $prescription?->patient_instructions) }}</textarea>
            </div>
            <div>
                <label class="label" for="instructions_language">Printed language</label>
                <select class="input" id="instructions_language" name="instructions_language">
                    @foreach (['en' => 'English', 'hi' => 'Hindi', 'bn' => 'Bengali'] as $code => $language)
                        <option value="{{ $code }}" @selected(old('instructions_language', $prescription?->instructions_language ?? 'en') === $code)>{{ $language }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="follow_up_on" label="Follow-up date" type="date" :value="$prescription?->follow_up_on?->toDateString()" />
        </section>
        <div class="text-right"><button class="btn btn-primary">Save prescription</button></div>
    </form>
</x-layouts.app>
