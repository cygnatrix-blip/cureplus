<x-layouts.app title="Records from other clinics">
    <x-page-header :title="$patient->name" subtitle="Records from other clinics. The patient must agree, and every view is logged.">
        <a class="btn btn-ghost" href="{{ route('app.patients.show', $patient) }}">Back</a>
    </x-page-header>
    @if (! $hasConsent)
        <section class="card card-body max-w-xl space-y-3">
            <p class="text-sm text-slate-600">Ask the patient first. Pressing the button emails them a code; they share it with you only if they agree.</p>
            <form method="POST" action="{{ route('app.patients.history.request', $patient) }}">@csrf<button class="btn btn-secondary">Email the patient a code</button></form>
            <form method="POST" action="{{ route('app.patients.history.confirm', $patient) }}" class="space-y-2">
                @csrf
                <x-field name="code" label="Code from the patient" inputmode="numeric" autocomplete="off" />
                <button class="btn btn-primary">Confirm consent</button>
            </form>
        </section>
    @else
        <section class="card">
            @forelse ($visits as $visit)
                <div class="border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                    <p class="font-medium text-slate-900">{{ $visit->visited_on->format('d M Y') }} · {{ $visit->organization?->name }} · {{ $visit->doctor?->name }}</p>
                    @if ($visit->diagnosis)<p>Diagnosis: {{ $visit->diagnosis }}</p>@endif
                    @if ($visit->complaints)<p class="text-slate-600">Complaints: {{ $visit->complaints }}</p>@endif
                </div>
            @empty
                <x-empty title="No records at other clinics" />
            @endforelse
        </section>
    @endif
</x-layouts.app>
