<x-layouts.app title="Edit hours">
    <x-page-header :title="'Edit hours: '.$availability->doctor?->name" :subtitle="\App\Models\DoctorAvailability::DAYS[$availability->weekday]" />
    <form method="POST" action="{{ route('app.calendar.availability.update', $availability) }}" class="card card-body mx-auto max-w-xl space-y-3">
        @csrf
        @method('PUT')
        <div class="grid gap-3 sm:grid-cols-2">
            <x-field name="start_time" label="From" type="time" :value="substr($availability->start_time, 0, 5)" required />
            <x-field name="end_time" label="To" type="time" :value="substr($availability->end_time, 0, 5)" required />
            <x-field name="slot_minutes" label="Minutes per patient" type="number" :value="$availability->slot_minutes" min="5" max="120" required />
            <div>
                <label class="label" for="location_id">Location</label>
                <select class="input" id="location_id" name="location_id" required>
                    @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id', $availability->location_id) === $location->id)>{{ $location->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <p class="text-xs text-slate-500">Appointments already booked keep their time. Only slots not yet booked follow the new hours.</p>
        <div class="flex flex-wrap gap-2">
            <button class="btn btn-primary">Save hours</button>
            <a class="btn btn-ghost" href="{{ route('app.calendar.index') }}">Cancel</a>
        </div>
    </form>
</x-layouts.app>
