@foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'status' => 'border-sky-200 bg-sky-50 text-sky-900', 'error' => 'border-rose-200 bg-rose-50 text-rose-900'] as $key => $classes)
    @if (session($key))
        <div x-data="toast" x-show="visible" role="status" class="mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
            <span>{{ session($key) }}</span>
            <button type="button" @click="dismiss" class="font-semibold" aria-label="Dismiss">&times;</button>
        </div>
    @endif
@endforeach
