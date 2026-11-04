<x-layouts.app title="Patients">
    <x-page-header title="Patients">
        <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Name, mobile, ID or ABHA" aria-label="Search patients" autofocus></form>
        @can('patients.manage')<a class="btn btn-primary" href="{{ route('app.patients.create') }}" data-shortcut="n">New patient</a>@endcan
        <a class="btn btn-secondary" href="{{ route('app.patients.export') }}">Export CSV</a>
    </x-page-header>
    <div class="card">
        @if ($patients->isEmpty())
            <x-empty title="No patients found" :text="$search ? 'Try another name or number.' : 'Register your first patient.'" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Mobile</th><th>Age</th><th>Gender</th><th></th></tr></thead>
                <tbody>
                    @foreach ($patients as $patient)
                        <tr>
                            <td class="font-mono text-xs">{{ $patient->patient_code }}</td>
                            <td class="font-medium text-slate-900">{{ $patient->name }} @if ($patient->is_demo)<span class="badge badge-amber">Sample</span>@endif</td>
                            <td>{{ $patient->mobile }}</td>
                            <td>{{ $patient->ageInYears() }}</td>
                            <td>{{ ucfirst((string) $patient->gender) }}</td>
                            <td class="text-right"><a class="btn btn-sm btn-secondary" href="{{ route('app.patients.show', $patient) }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $patients->links() }}</div>
</x-layouts.app>
