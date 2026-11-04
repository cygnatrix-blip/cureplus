<x-layouts.app title="Doctor calendar">
    <x-page-header title="Doctor calendar" subtitle="Bookings at any organization block the doctor's time everywhere." />
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.calendar.availability.store') }}" class="card card-body space-y-3">
            @csrf
            <h3 class="font-semibold text-slate-900">Add weekly hours</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="doctor_user_id">Doctor</label>
                    <select class="input" id="doctor_user_id" name="doctor_user_id" required>
                        @foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected((int) old('doctor_user_id') === $doctor->id)>{{ $doctor->name }}</option>@endforeach
                    </select>
                    @error('doctor_user_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="location_id">Location</label>
                    <select class="input" id="location_id" name="location_id" required>
                        @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id') === $location->id)>{{ $location->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="weekday">Day</label>
                    <select class="input" id="weekday" name="weekday">
                        @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $index => $day)<option value="{{ $index }}" @selected((int) old('weekday', 1) === $index)>{{ $day }}</option>@endforeach
                    </select>
                </div>
                <x-field name="slot_minutes" label="Minutes per patient" type="number" value="15" min="5" max="120" required />
                <x-field name="start_time" label="From" type="time" required />
                <x-field name="end_time" label="To" type="time" required />
            </div>
            <button class="btn btn-primary">Add hours</button>
        </form>
        <form method="POST" action="{{ route('app.calendar.blocks.store') }}" class="card card-body space-y-3">
            @csrf
            <h3 class="font-semibold text-slate-900">Block time (leave or holiday)</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="block_doctor">Doctor</label>
                    <select class="input" id="block_doctor" name="doctor_user_id">
                        <option value="">Everyone at the location</option>
                        @foreach ($doctors as $doctor)<option value="{{ $doctor->id }}">{{ $doctor->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="block_location">Location</label>
                    <select class="input" id="block_location" name="location_id">
                        <option value="">All locations</option>
                        @foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                    </select>
                </div>
                <x-field name="starts_at" label="From" type="datetime-local" required />
                <x-field name="ends_at" label="To" type="datetime-local" required />
            </div>
            <x-field name="reason" label="Reason (optional)" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="applies_everywhere" value="1"> I am this doctor: block me at every organization</label>
            <button class="btn btn-primary">Block time</button>
        </form>
    </div>

    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Weekly hours</h3></div>
        @if ($availability->isEmpty())
            <x-empty title="No hours set" text="Patients can only book slots once hours are added." />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Doctor</th><th>Location</th><th>Day</th><th>Time</th><th>Slot</th><th></th></tr></thead>
                <tbody>
                    @foreach ($availability as $row)
                        <tr>
                            <td>{{ $row->doctor?->name }}</td>
                            <td>{{ $row->location?->name }}</td>
                            <td>{{ ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$row->weekday] }}</td>
                            <td>{{ substr($row->start_time, 0, 5) }}–{{ substr($row->end_time, 0, 5) }}</td>
                            <td>{{ $row->slot_minutes }} min</td>
                            <td class="text-right"><form method="POST" action="{{ route('app.calendar.availability.destroy', $row) }}" data-confirm="Remove these hours?">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Remove</button></form></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $availability->links() }}</div>

    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Upcoming blocks</h3></div>
        @forelse ($blocks as $block)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                <span>{{ $block->doctor?->name ?? 'Everyone' }} · {{ $block->location?->name ?? 'All locations' }} · {{ $block->starts_at->format('d M H:i') }} to {{ $block->ends_at->format('d M H:i') }} @if ($block->applies_everywhere)<span class="badge badge-amber">All organizations</span>@endif {{ $block->reason }}</span>
                <form method="POST" action="{{ route('app.calendar.blocks.destroy', $block) }}" data-confirm="Remove this block?">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Remove</button></form>
            </div>
        @empty
            <x-empty title="No upcoming blocks" />
        @endforelse
    </section>
</x-layouts.app>
