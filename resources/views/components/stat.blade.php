@props(['label', 'value', 'tone' => null])
<div class="card card-body">
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p @class(['mt-1 text-2xl font-semibold', 'text-rose-700' => $tone === 'bad', 'text-amber-700' => $tone === 'warn', 'text-slate-900' => ! $tone])>{{ $value }}</p>
</div>
