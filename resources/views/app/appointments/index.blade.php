<x-layouts.app title="Appointments">
    <x-page-header title="Appointments">
        <form method="GET" class="flex flex-wrap gap-2">
            <input class="input w-auto" type="date" name="date" value="{{ $date->toDateString() }}" data-auto-submit aria-label="Date">
            <select class="input w-auto" name="doctor" data-auto-submit aria-label="Doctor">
                <option value="">All doctors</option>
                @foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected($doctorId === $doctor->id)>{{ $doctor->name }}</option>@endforeach
            </select>
            <select class="input w-auto" name="status" data-auto-submit aria-label="Status">
                <option value="">All statuses</option>
                @foreach (\App\Enums\AppointmentStatus::cases() as $case)<option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>@endforeach
            </select>
        </form>
        @can('appointments.manage')<a class="btn btn-primary" href="{{ route('app.appointments.create') }}" data-shortcut="b">Book</a>@endcan
    </x-page-header>
    <div class="card">
        @if ($appointments->isEmpty())
            <x-empty title="No appointments for this day" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Token</th><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($appointments as $appointment)
                        <tr>
                            <td class="font-semibold">{{ $appointment->token_number }}</td>
                            <td class="whitespace-nowrap">{{ $appointment->starts_at?->format('H:i') ?? 'Walk-in' }}</td>
                            <td><a class="text-brand-700 underline" href="{{ route('app.patients.show', $appointment->patient_id) }}">{{ $appointment->patient?->name }}</a></td>
                            <td>{{ $appointment->doctor?->name }}</td>
                            <td>{{ $appointment->visit_type->label() }}</td>
                            <td><span class="badge badge-slate">{{ $appointment->status->label() }}</span></td>
                            <td class="text-right">
                                @can('update', $appointment)
                                    @if (in_array($appointment->status, [\App\Enums\AppointmentStatus::Booked, \App\Enums\AppointmentStatus::CheckedIn], true))
                                        <div class="flex justify-end gap-1">
                                            <a class="btn btn-sm btn-secondary" href="{{ route('app.appointments.edit', $appointment) }}">Reschedule</a>
                                            <form method="POST" action="{{ route('app.appointments.cancel', $appointment) }}" class="flex gap-1" data-confirm="Cancel this appointment?">
                                                @csrf
                                                <input class="input min-h-9 w-32 py-1" name="reason" placeholder="Reason" required aria-label="Reason for cancelling">
                                                <button class="btn btn-sm btn-danger">Cancel</button>
                                            </form>
                                        </div>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $appointments->links() }}</div>
</x-layouts.app>
