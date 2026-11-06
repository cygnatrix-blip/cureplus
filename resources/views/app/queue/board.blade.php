@if ($groups->isEmpty() && $earlier->isEmpty())
    <div class="card"><x-empty title="The queue is empty" text="Book or add a walk-in to start." /></div>
@endif
<div class="grid gap-4 lg:grid-cols-2">
    @foreach ($groups as $rows)
        <section class="card">
            <div class="card-header"><h3 class="card-title">{{ $rows->first()->doctor?->name }}</h3><span class="text-xs text-slate-500">{{ $rows->count() }} today</span></div>
            @foreach ($rows as $appointment)
                @include('app.queue.row', ['showDate' => false])
            @endforeach
        </section>
    @endforeach
</div>
@if ($earlier->isNotEmpty())
    <section class="card mt-4 border-amber-300" aria-labelledby="queue-earlier">
        <div class="card-header bg-amber-50">
            <h3 class="card-title" id="queue-earlier">Not finished from earlier days</h3>
            <span class="text-xs text-amber-900">{{ $earlier->count() }} to close: complete the consultation or mark a no-show</span>
        </div>
        @foreach ($earlier as $appointment)
            @include('app.queue.row', ['showDate' => true])
        @endforeach
    </section>
@endif
