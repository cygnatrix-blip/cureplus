<x-layouts.patient :title="__('Appointments')">
    <h1 class="mb-4 text-xl font-bold text-slate-900">{{ __('Appointments') }}</h1>
    <div class="space-y-3">
        @forelse ($appointments as $appointment)
            @include('patient.partials.appointment-card', ['appointment' => $appointment])
        @empty
            <div class="card"><x-empty :title="__('Nothing here yet.')" :text="__('To book, open the link your clinic gave you.')" /></div>
        @endforelse
    </div>
</x-layouts.patient>
