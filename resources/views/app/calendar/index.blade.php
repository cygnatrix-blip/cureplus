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
                <x-field name="slot_minutes" label="Minutes per patient" type="number" value="15" min="5" max="120" required />
                <div class="hidden sm:block"></div>
                <x-field name="start_time" label="From" type="time" required />
                <x-field name="end_time" label="To" type="time" required />
            </div>
            @include('app.calendar.day-picker', ['name' => 'weekdays', 'legend' => 'Days', 'checked' => old('weekdays', [1])])
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

    <form method="POST" action="{{ route('app.calendar.availability.copy') }}" class="card card-body mt-4 space-y-3">
        @csrf
        <div>
            <h3 class="font-semibold text-slate-900">Copy a day to other days</h3>
            <p class="text-sm text-slate-600">Copies every session the doctor has here that day, such as morning and evening, with the same minutes per patient.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label" for="copy_doctor">Doctor</label>
                <select class="input" id="copy_doctor" name="doctor_user_id" required>
                    @foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected((int) old('doctor_user_id') === $doctor->id)>{{ $doctor->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="from_weekday">Copy</label>
                <select class="input" id="from_weekday" name="from_weekday">
                    @foreach (\App\Models\DoctorAvailability::DAYS as $index => $day)<option value="{{ $index }}" @selected((int) old('from_weekday', 1) === $index)>{{ $day }}</option>@endforeach
                </select>
                @error('from_weekday')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        @include('app.calendar.day-picker', ['name' => 'to_weekdays', 'legend' => 'To', 'checked' => old('to_weekdays', [])])
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="replace" value="1" @checked(old('replace'))> Replace the hours those days already have here</label>
        <button class="btn btn-secondary">Copy hours</button>
    </form>

    <section class="card mt-4">
        <div class="card-header"><h3 class="card-title">Weekly hours</h3></div>
        @if ($availability->isEmpty())
            <x-empty title="No hours set" text="Patients can only book slots once hours are added." />
        @else
            @foreach ($availability as $rows)
                <div class="border-b border-slate-100 last:border-b-0">
                    <h4 class="bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-900 sm:px-5">{{ $rows->first()->doctor?->name }}</h4>
                    <div class="table-wrap"><table class="table">
                        <thead class="sr-only"><tr><th>Day</th><th>Time</th><th>Location</th><th>Slot</th><th>Actions</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="w-28 font-medium">{{ \App\Models\DoctorAvailability::DAYS[$row->weekday] }}</td>
                                    <td>{{ substr($row->start_time, 0, 5) }}–{{ substr($row->end_time, 0, 5) }}</td>
                                    <td>{{ $row->location?->name }}</td>
                                    <td>{{ $row->slot_minutes }} min</td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <a class="btn btn-sm btn-secondary" href="{{ route('app.calendar.availability.edit', $row) }}">Edit</a>
                                            <form method="POST" action="{{ route('app.calendar.availability.destroy', $row) }}" data-confirm="Remove these hours?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Remove</button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endforeach
        @endif
    </section>

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
