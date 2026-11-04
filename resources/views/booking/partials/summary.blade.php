<aside class="card overflow-hidden lg:sticky lg:top-20">
    <div class="bg-brand-700 px-5 py-3 text-sm font-semibold text-white">{{ __('Your appointment') }}</div>
    <dl class="space-y-3 p-5 text-sm">
        @if ($doctor)
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">{{ $doctor['initials'] }}</span>
                <div class="min-w-0">
                    <dt class="sr-only">{{ __('Doctor') }}</dt>
                    <dd class="truncate font-semibold text-slate-900">{{ $doctor['name'] }}</dd>
                    @if ($doctor['specialty'])<dd class="truncate text-slate-500">{{ $doctor['specialty'] }}</dd>@endif
                </div>
            </div>
        @endif
        @isset($startsAt)
            <div class="flex items-start gap-3 text-slate-700"><x-icon name="calendar" class="h-5 w-5 flex-none text-brand-600" /><div><dt class="sr-only">{{ __('Date') }}</dt><dd class="font-medium">{{ $startsAt->format('l, d M Y') }}</dd><dd class="text-slate-500">{{ $startsAt->format('h:i A') }}</dd></div></div>
        @endisset
        @if ($location)
            <div class="flex items-start gap-3 text-slate-700"><x-icon name="pin" class="h-5 w-5 flex-none text-brand-600" /><div><dt class="sr-only">{{ __('Location') }}</dt><dd class="font-medium">{{ $location->name }}</dd><dd class="text-slate-500">{{ collect([$location->address_line, $location->city])->filter()->join(', ') }}</dd></div></div>
        @endif
        @if (($doctor['fee'] ?? null) !== null)
            <div class="flex items-center justify-between border-t border-slate-100 pt-3"><dt class="text-slate-500">{{ __('Consultation fee') }}</dt><dd class="font-semibold text-slate-900">{{ $doctor['fee'] }}</dd></div>
        @endif
    </dl>
</aside>
