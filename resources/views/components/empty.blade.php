@props(['title', 'text' => null])
<div class="px-4 py-12 text-center">
    <p class="font-medium text-slate-900">{{ $title }}</p>
    @if ($text)<p class="mt-1 text-sm text-slate-500">{{ $text }}</p>@endif
    {{ $slot }}
</div>
