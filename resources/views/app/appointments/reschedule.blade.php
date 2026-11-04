<x-layouts.app title="Reschedule">
    <div class="mx-auto max-w-3xl space-y-4">
        <section class="card card-body">
            <p class="font-semibold text-slate-900">{{ $appointment->patient?->name }} with {{ $appointment->doctor?->name }}</p>
            <p class="text-sm text-slate-500">Now: {{ $appointment->scheduled_on->format('d M Y') }} {{ $appointment->starts_at?->format('H:i') }}</p>
            <form method="GET" class="mt-3"><input class="input w-auto" type="date" name="date" value="{{ $date->toDateString() }}" min="{{ today()->toDateString() }}" data-auto-submit aria-label="New date"></form>
        </section>
        <form method="POST" action="{{ route('app.appointments.update', $appointment) }}" class="card card-body space-y-4">
            @csrf @method('PUT')
            @if ($slots === [])
                <x-empty title="No hours on this day" text="Choose another date." />
            @else
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                    @foreach ($slots as $slot)
                        <label @class(['flex min-h-11 items-center justify-center rounded-lg border text-sm', 'cursor-pointer border-slate-300 has-checked:border-brand-600 has-checked:bg-brand-50' => $slot['available'], 'cursor-not-allowed border-slate-100 bg-slate-100 text-slate-400 line-through' => ! $slot['available']])>
                            <input class="sr-only" type="radio" name="starts_at" value="{{ $slot['starts_at']->format('Y-m-d H:i') }}" @disabled(! $slot['available'])>
                            {{ $slot['starts_at']->format('H:i') }}
                        </label>
                    @endforeach
                </div>
                @error('starts_at')<p class="field-error">{{ $message }}</p>@enderror
                <div class="text-right"><button class="btn btn-primary">Move appointment</button></div>
            @endif
        </form>
    </div>
</x-layouts.app>
