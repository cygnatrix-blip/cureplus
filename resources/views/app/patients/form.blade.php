<x-layouts.app :title="$patient->exists ? 'Edit patient' : 'New patient'">
    <form method="POST" action="{{ $patient->exists ? route('app.patients.update', $patient) : route('app.patients.store') }}" class="card card-body mx-auto max-w-2xl space-y-4">
        @csrf
        @if ($patient->exists) @method('PUT') @endif
        @if ($duplicates->isNotEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                <p class="font-medium">This patient may already be registered:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($duplicates as $duplicate)
                        <li><a class="underline" href="{{ route('app.patients.show', $duplicate) }}">{{ $duplicate->name }} · {{ $duplicate->patient_code }}</a></li>
                    @endforeach
                </ul>
                <label class="mt-2 flex items-center gap-2"><input type="checkbox" name="confirm_new" value="1"> This is a different person</label>
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Full name" :value="$patient->name" required autofocus />
            <x-field name="mobile" label="Mobile" type="tel" inputmode="numeric" :value="$patient->mobile" required />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="age" label="Age" type="number" inputmode="numeric" min="0" max="130" :value="$patient->ageInYears()" />
            <div>
                <label for="gender" class="label">Gender</label>
                <select id="gender" name="gender" class="input">
                    <option value="">Select</option>
                    @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $patient->gender) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="relation" label="Relation to account holder" :value="$patient->relation" placeholder="Self, son, mother…" />
        </div>
        <details class="rounded-lg border border-slate-200 p-3" @if ($patient->exists) open @endif>
            <summary class="cursor-pointer text-sm font-medium">More details</summary>
            <div class="mt-3 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="date_of_birth" label="Date of birth" type="date" :value="$patient->date_of_birth?->toDateString()" />
                    <x-field name="email" label="Email" type="email" :value="$patient->email" hint="Lets the patient see their records in the portal." />
                </div>
                <x-field name="address" label="Address" :value="$patient->address" />
                <x-field name="abha_id" label="ABHA number" :value="$patient->abha_id" inputmode="numeric" hint="14 digits, if the patient has one." />
                <x-field name="allergies" label="Known allergies" :value="$patient->allergies" />
            </div>
        </details>
        <div class="flex flex-wrap justify-end gap-2">
            <a class="btn btn-ghost" href="{{ route('app.patients.index') }}">Cancel</a>
            @unless ($patient->exists)<button class="btn btn-secondary" name="next" value="book">Save and book</button>@endunless
            <button class="btn btn-primary">Save</button>
        </div>
    </form>
</x-layouts.app>
