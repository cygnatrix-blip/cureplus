<x-layouts.app title="Book appointment">
    <div class="mx-auto max-w-3xl space-y-4">
        <section class="card card-body" x-data="patientPicker" data-url="{{ route('app.patients.search') }}" data-target="{{ route('app.appointments.create') }}">
            <h2 class="font-semibold text-slate-900">Patient</h2>
            @if ($patient)
                <p class="mt-1 text-sm">{{ $patient->name }} · {{ $patient->mobile }} · {{ $patient->patient_code }} <a class="text-brand-700 underline" href="{{ route('app.appointments.create') }}">Change</a></p>
            @else
                <input class="input mt-2" type="search" placeholder="Search by name, mobile or ID" aria-label="Search patient" @input="search" autofocus>
                <ul class="mt-2 divide-y divide-slate-100">
                    <template x-for="result in results" :key="result.id">
                        <li><a class="block px-2 py-3 text-sm hover:bg-slate-50" :href="result.url" x-text="result.label"></a></li>
                    </template>
                </ul>
                <a class="btn btn-secondary mt-2" href="{{ route('app.patients.create') }}">Register new patient</a>
            @endif
        </section>

        @if ($patient)
            <section class="card card-body">
                <h2 class="font-semibold text-slate-900">Doctor and date</h2>
                <form method="GET" class="mt-2 flex flex-wrap gap-2">
                    <input type="hidden" name="patient" value="{{ $patient->id }}">
                    <select class="input w-auto" name="doctor" data-auto-submit aria-label="Doctor">
                        <option value="">Choose a doctor</option>
                        @foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected($doctorId === $doctor->id)>{{ $doctor->name }}</option>@endforeach
                    </select>
                    <input class="input w-auto" type="date" name="date" value="{{ $date->toDateString() }}" min="{{ today()->toDateString() }}" data-auto-submit aria-label="Date">
                </form>
                @if ($doctors->isEmpty())<p class="mt-2 text-sm text-slate-600">No doctor is assigned to this location yet.</p>@endif
            </section>

            @if ($doctorId)
                <form method="POST" action="{{ route('app.appointments.store') }}" class="card card-body space-y-4">
                    @csrf
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <input type="hidden" name="doctor_user_id" value="{{ $doctorId }}">
                    <div>
                        <label for="visit_type" class="label">Visit type</label>
                        <select id="visit_type" name="visit_type" class="input">
                            @foreach (\App\Enums\VisitType::cases() as $case)<option value="{{ $case->value }}" @selected(old('visit_type') === $case->value)>{{ $case->label() }}</option>@endforeach
                        </select>
                    </div>
                    @if ($offersVideo)
                        <fieldset>
                            <legend class="label">How will they meet?</legend>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (\App\Enums\AppointmentMode::cases() as $case)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 p-3 text-sm has-checked:border-brand-600 has-checked:bg-brand-50">
                                        <input type="radio" name="mode" value="{{ $case->value }}" @checked(old('mode', 'in_person') === $case->value)>{{ $case->label() }}
                                    </label>
                                @endforeach
                            </div>
                            <label class="mt-2 flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="video_consent" value="1" class="mt-1" @checked(old('video_consent'))>
                                <span>For a video call: the patient has agreed to be seen by video</span>
                            </label>
                            @error('video_consent')<p class="field-error">{{ $message }}</p>@enderror
                            <p class="hint">A walk-in is always at the clinic.</p>
                            @error('mode')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif
                    <fieldset>
                        <legend class="label">Slot</legend>
                        @if ($slots === [])
                            <p class="text-sm text-slate-600">No hours are set for this doctor on this day. You can still add a walk-in.</p>
                        @else
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                @foreach ($slots as $slot)
                                    <label @class(['flex min-h-11 items-center justify-center rounded-lg border text-sm', 'cursor-pointer border-slate-300 has-checked:border-brand-600 has-checked:bg-brand-50' => $slot['available'], 'cursor-not-allowed border-slate-100 bg-slate-100 text-slate-400 line-through' => ! $slot['available']])>
                                        <input class="sr-only" type="radio" name="starts_at" value="{{ $slot['starts_at']->format('Y-m-d H:i') }}" @disabled(! $slot['available'])>
                                        {{ $slot['starts_at']->format('H:i') }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('starts_at')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button class="btn btn-secondary" name="walk_in" value="1">Add as walk-in today</button>
                        @if ($slots !== [])<button class="btn btn-primary">Book slot</button>@endif
                    </div>
                </form>
            @endif
        @endif
    </div>
</x-layouts.app>
