<x-layouts.app title="Prescriptions">
    <x-page-header title="Prescriptions" :subtitle="$onlyOwn ? 'The ones you wrote' : 'Every prescription in your practice'" />
    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2">
            <label class="label" for="q">Patient</label>
            <input class="input" id="q" type="search" name="q" value="{{ $search }}" placeholder="Name, patient ID or mobile">
        </div>
        @unless ($onlyOwn)
            <div>
                <label class="label" for="doctor">Doctor</label>
                <select class="input" id="doctor" name="doctor">
                    <option value="">All doctors</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->doctor_user_id }}" @selected((int) $doctorId === (int) $doctor->doctor_user_id)>{{ $doctor->doctor_name }}</option>
                    @endforeach
                </select>
            </div>
        @endunless
        <div>
            <label class="label" for="from">From</label>
            <input class="input" id="from" type="date" name="from" value="{{ $from }}">
        </div>
        <div>
            <label class="label" for="to">To</label>
            <input class="input" id="to" type="date" name="to" value="{{ $to }}">
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
            <button class="btn btn-primary">Search</button>
            <a class="btn btn-ghost" href="{{ route('app.prescriptions.index') }}">Clear</a>
        </div>
    </form>
    <div class="card">
        @if ($prescriptions->isEmpty())
            <x-empty title="No prescriptions found" text="Prescriptions appear here once a doctor writes one during a consultation." />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Branch</th><th></th></tr></thead>
                <tbody>
                    @foreach ($prescriptions as $prescription)
                        <tr>
                            <td class="whitespace-nowrap">{{ ($prescription->issued_at ?? $prescription->created_at)?->format('d M Y') }}</td>
                            <td><span class="font-medium text-slate-900">{{ $prescription->patient?->name }}</span> <span class="block text-xs text-slate-500">{{ $prescription->patient?->patient_code }}</span></td>
                            <td>{{ $prescription->doctor_name }}</td>
                            <td>{{ $prescription->location?->name }}</td>
                            <td class="text-right">
                                @if ($tenant->organization()?->isActive())
                                    <a class="btn btn-sm btn-secondary" href="{{ route('app.prescriptions.show', $prescription) }}">Open</a>
                                @else
                                    <a class="btn btn-sm btn-secondary" href="{{ route('app.prescriptions.edit', $prescription->visit_id) }}">Open</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $prescriptions->links() }}</div>
</x-layouts.app>
