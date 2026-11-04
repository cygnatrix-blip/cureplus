@props(['title', 'subtitle' => null])
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
        @if ($subtitle)<p class="text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
