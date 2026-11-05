<x-layouts.app :title="'Consultation: '.$visit->patient->name">
    <x-page-header :title="$visit->patient->name" :subtitle="$visit->patient->patient_code.' · '.($visit->patient->ageInYears() ?? '?').' yrs · '.ucfirst((string) $visit->patient->gender)">
        @if ($visit->appointment)@include('app.appointments.partials.join-video', ['appointment' => $visit->appointment])@endif
        @can('prescribe', $visit)<a class="btn btn-primary" href="{{ route('app.prescriptions.edit', $visit) }}">{{ $visit->prescription ? 'Edit prescription' : 'Write prescription' }}</a>@endcan
        @if ($visit->prescription)<a class="btn btn-secondary" href="{{ route('app.prescriptions.show', $visit->prescription) }}">View prescription</a>@endif
        @if ($canEdit)<form method="POST" action="{{ route('app.visits.complete', $visit) }}" data-confirm="Complete this consultation? It cannot be edited afterwards.">@csrf<button class="btn btn-secondary">Complete</button></form>@endif
    </x-page-header>
    @if ($visit->patient->allergies && $canViewNotes)
        <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-900" role="alert">Allergies: {{ $visit->patient->allergies }}</p>
    @endif
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @can('recordVitals', $visit)
                <form method="POST" action="{{ route('app.visits.vitals', $visit) }}" class="card card-body">
                    @csrf
                    <h3 class="mb-3 font-semibold text-slate-900">Vitals</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <x-field name="bp_systolic" label="BP systolic" inputmode="numeric" :value="$visit->vital?->bp_systolic" />
                        <x-field name="bp_diastolic" label="BP diastolic" inputmode="numeric" :value="$visit->vital?->bp_diastolic" />
                        <x-field name="pulse" label="Pulse" inputmode="numeric" :value="$visit->vital?->pulse" />
                        <x-field name="temperature_f" label="Temp (°F)" inputmode="decimal" :value="$visit->vital?->temperature_f" />
                        <x-field name="spo2" label="SpO₂ %" inputmode="numeric" :value="$visit->vital?->spo2" />
                        <x-field name="weight_kg" label="Weight (kg)" inputmode="decimal" :value="$visit->vital?->weight_kg" />
                        <x-field name="height_cm" label="Height (cm)" inputmode="decimal" :value="$visit->vital?->height_cm" />
                    </div>
                    <button class="btn btn-secondary mt-3">Save vitals</button>
                </form>
            @endcan
            @if ($canViewNotes)
                <form method="POST" action="{{ route('app.visits.update', $visit) }}" class="card card-body space-y-3" data-emr-form>
                    @csrf @method('PUT')
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-900">Consultation notes</h3>
                        @if ($canEdit && $templates->isNotEmpty())
                            <select class="input w-auto" data-emr-template aria-label="Apply template">
                                <option value="">Use a template</option>
                                @foreach ($templates as $template)<option value="{{ json_encode($template->body) }}">{{ $template->specialty }}: {{ $template->name }}</option>@endforeach
                            </select>
                        @endif
                    </div>
                    @foreach (['complaints' => 'Complaints', 'history' => 'History', 'diagnosis' => 'Diagnosis', 'notes' => 'Notes'] as $field => $label)
                        <div>
                            <label class="label" for="{{ $field }}">{{ $label }}</label>
                            <textarea class="input" id="{{ $field }}" name="{{ $field }}" rows="3" @disabled(! $canEdit)>{{ old($field, $visit->{$field}) }}</textarea>
                            @error($field)<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @if ($canEdit)<button class="btn btn-primary">Save notes</button>@endif
                </form>
                <section class="card card-body">
                    <h3 class="font-semibold text-slate-900">Attachments</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        @forelse ($visit->attachments as $attachment)
                            <li><a class="text-brand-700 underline" href="{{ $attachmentLinks[$attachment->id] }}">{{ $attachment->original_name }}</a></li>
                        @empty
                            <li class="text-slate-500">Nothing attached.</li>
                        @endforelse
                    </ul>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('app.visits.attachments.store', $visit) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <div><label class="label" for="file">PDF or image, up to 10 MB</label><input class="input" id="file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>@error('file')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label class="label" for="category">Type</label><select class="input" id="category" name="category"><option value="lab_report">Lab report</option><option value="imaging">Imaging</option><option value="other">Other</option></select></div>
                            <button class="btn btn-secondary">Upload</button>
                        </form>
                    @endif
                </section>
            @else
                <p class="card card-body text-sm text-slate-600">Consultation notes are visible to clinicians only.</p>
            @endif
        </div>
        @if ($canViewNotes)
            <aside class="card self-start">
                <div class="card-header"><h3 class="card-title">Past visits</h3></div>
                @forelse ($pastVisits as $past)
                    <a class="block border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0" href="{{ route('app.visits.show', $past) }}">
                        <span class="font-medium">{{ $past->visited_on->format('d M Y') }}</span> · {{ $past->doctor?->name }}
                        <span class="block truncate text-slate-500">{{ $past->diagnosis }}</span>
                    </a>
                @empty
                    <x-empty title="First visit" />
                @endforelse
            </aside>
        @endif
    </div>
</x-layouts.app>
