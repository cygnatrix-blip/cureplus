<x-layouts.app title="Queue">
    <x-page-header title="Today's queue" :subtitle="$location?->name">
        @if ($location?->display_token)<a class="btn btn-secondary" href="{{ route('display.show', $location->display_token) }}" target="_blank" rel="noopener">Open TV display</a>@endif
        @can('appointments.manage')<a class="btn btn-primary" href="{{ route('app.appointments.create') }}" data-shortcut="w">Add patient</a>@endcan
    </x-page-header>
    <div id="queue-board" data-queue-board="{{ route('app.queue.index', ['fragment' => 1]) }}">
        @include('app.queue.board')
    </div>
</x-layouts.app>
