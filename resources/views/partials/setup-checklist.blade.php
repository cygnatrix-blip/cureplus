@php($remaining = collect($setup)->where('done', false))
@if ($remaining->isNotEmpty())
    <section class="card mb-4" aria-labelledby="setup-heading">
        <div class="card-header flex items-center justify-between gap-2">
            <h2 id="setup-heading" class="card-title">Get your practice ready</h2>
            <span class="text-sm text-slate-500">{{ count($setup) - $remaining->count() }} of {{ count($setup) }} done</span>
        </div>
        <ul>
            @foreach ($setup as $step)
                <li class="border-b border-slate-100 last:border-b-0">
                    <a href="{{ $step['url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 sm:px-5">
                        <span @class(['flex h-7 w-7 flex-none items-center justify-center rounded-full', 'bg-emerald-600 text-white' => $step['done'], 'border-2 border-slate-300 text-transparent' => ! $step['done']])><x-icon name="check" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1"><span @class(['block text-sm font-medium', 'text-slate-500 line-through' => $step['done'], 'text-slate-900' => ! $step['done']])>{{ $step['label'] }}</span>@unless ($step['done'])<span class="block text-xs text-slate-500">{{ $step['hint'] }}</span>@endunless</span>
                        @unless ($step['done'])<x-icon name="arrow" class="h-4 w-4 flex-none text-slate-400" />@endunless
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
