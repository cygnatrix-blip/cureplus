@php($steps = [1 => __('Doctor and time'), 2 => __('Your details'), 3 => __('Confirmed')])
<ol class="flex items-center gap-2 text-sm" aria-label="{{ __('Booking steps') }}">
    @foreach ($steps as $number => $label)
        <li @class(['flex items-center gap-2', 'flex-1' => $number < 3])>
            <span @class(['flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-semibold', 'bg-brand-600 text-white' => $number <= $current, 'bg-slate-200 text-slate-600' => $number > $current])>{{ $number }}</span>
            <span @class(['font-medium', 'text-slate-900' => $number === $current, 'text-slate-500' => $number !== $current, 'hidden sm:inline' => $number !== $current])>{{ $label }}</span>
            @if ($number < 3)<span class="h-px flex-1 bg-slate-200"></span>@endif
        </li>
    @endforeach
</ol>
