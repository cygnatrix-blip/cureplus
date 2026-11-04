<x-layouts.app :title="$patient->name">
    <x-page-header :title="$patient->name" :subtitle="$patient->patient_code.' · '.$patient->mobile">
        @can('appointments.manage')<a class="btn btn-primary" href="{{ route('app.appointments.create', ['patient' => $patient->id]) }}">Book</a>@endcan
        @can('viewHistory', $patient)<a class="btn btn-secondary" href="{{ route('app.patients.history', $patient) }}">Other clinics' records</a>@endcan
        @can('update', $patient)<a class="btn btn-secondary" href="{{ route('app.patients.edit', $patient) }}">Edit</a>@endcan
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card card-body">
            <h3 class="font-semibold text-slate-900">Details</h3>
            <dl class="mt-2 space-y-2 text-sm">
                <div><dt class="text-slate-500">Age and gender</dt><dd>{{ $patient->ageInYears() ?? 'Age unknown' }}{{ $patient->dob_is_estimated ? ' (approx.)' : '' }} · {{ ucfirst((string) $patient->gender) }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd>{{ $patient->email ?? 'Not on file' }}</dd></div>
                <div><dt class="text-slate-500">ABHA</dt><dd>{{ $patient->abha_id ? 'On file' : 'Not on file' }}</dd></div>
                @can('consultations.manage')<div><dt class="text-slate-500">Allergies</dt><dd>{{ $patient->allergies ?? 'None recorded' }}</dd></div>@endcan
            </dl>
            @if ($family->isNotEmpty())
                <h3 class="mt-4 font-semibold text-slate-900">Family on this number</h3>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($family as $member)
                        <li><a class="text-brand-700 underline" href="{{ route('app.patients.show', $member) }}">{{ $member->name }}</a> {{ $member->relation }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
        <section class="card lg:col-span-2">
            <div class="card-header"><h3 class="card-title">Appointments</h3></div>
            @forelse ($appointments as $appointment)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                    <span>{{ $appointment->scheduled_on->format('d M Y') }} {{ $appointment->starts_at?->format('H:i') }} · {{ $appointment->doctor?->name }} · Token {{ $appointment->token_number }}</span>
                    <span class="badge badge-slate">{{ $appointment->status->label() }}</span>
                </div>
            @empty
                <x-empty title="No appointments yet" />
            @endforelse
        </section>
    </div>
    @if ($visits !== null)
        <section class="card mt-4">
            <div class="card-header"><h3 class="card-title">Visits</h3></div>
            @forelse ($visits as $visit)
                <a class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0 sm:px-5" href="{{ route('app.visits.show', $visit) }}">
                    <span>{{ $visit->visited_on->format('d M Y') }} · {{ $visit->doctor?->name }}</span>
                    <span class="min-w-0 truncate text-slate-500">{{ $visit->diagnosis }}</span>
                </a>
            @empty
                <x-empty title="No visits yet" />
            @endforelse
        </section>
        <div class="mt-4">{{ $visits->links() }}</div>
    @endif
</x-layouts.app>
