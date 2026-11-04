@php
    $tagline = $organization->branding['tagline'] ?? null;
    $about = $organization->branding['about'] ?? null;
    $highlights = $organization->branding['highlights'] ?? [];
    $phone = $organization->branding['contact_phone'] ?? null;
    $contactEmail = $organization->branding['contact_email'] ?? null;
    $query = fn (array $change) => array_filter(array_merge(['location' => $locationId, 'doctor' => $preselected ? null : $doctorId, 'date' => $date->toDateString()], $change));
    $selfRoute = fn (array $change) => $preselected ? route('booking.doctor', [$organization->slug, $preselected->doctor_slug] + $query($change)) : route('booking.show', [$organization->slug] + $query($change));
    $open = collect($slots)->where('available', true);
    $groups = ['Morning' => $open->filter(fn ($slot) => $slot['starts_at']->hour < 12), 'Afternoon' => $open->filter(fn ($slot) => $slot['starts_at']->hour >= 12 && $slot['starts_at']->hour < 17), 'Evening' => $open->filter(fn ($slot) => $slot['starts_at']->hour >= 17)];
    $location = $locations->firstWhere('id', $locationId);
@endphp
<x-layouts.booking :organization="$organization">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-linear-to-br from-brand-800 to-brand-600 text-white">
        @if ($organization->cover_image_path)
            <img src="{{ route('brand.cover', $organization->slug) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25">
        @endif
        <div class="relative mx-auto max-w-6xl px-4 py-10 sm:py-14">
            <h1 class="max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">{{ $tagline ?: __('Book your appointment at :clinic', ['clinic' => $organization->name]) }}</h1>
            @if ($about)<p class="mt-3 max-w-2xl text-white/90">{{ $about }}</p>@endif
            <ul class="mt-5 flex flex-wrap gap-2 text-sm">
                @foreach ($highlights ?: [__('Confirmation by email'), __('Choose your own time'), __('Your records stay private')] as $highlight)
                    <li class="flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 font-medium backdrop-blur"><x-icon name="check" class="h-4 w-4 text-accent-100" />{{ $highlight }}</li>
                @endforeach
            </ul>
            @if ($phone || $contactEmail)
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/90">
                    @if ($phone)<a class="flex items-center gap-2 hover:underline" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"><x-icon name="phone" class="h-4 w-4" />{{ $phone }}</a>@endif
                    @if ($contactEmail)<a class="flex items-center gap-2 hover:underline" href="mailto:{{ $contactEmail }}"><x-icon name="mail" class="h-4 w-4" />{{ $contactEmail }}</a>@endif
                </div>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-6">
        @include('partials.flash')
        <div class="mb-5">@include('booking.partials.steps', ['current' => 1])</div>

        @if ($locations->isEmpty() || ($doctors->isEmpty() && $locationId && ! $preselected))
            <div class="card"><x-empty :title="__('Online booking is not open right now')" :text="__('Please contact the clinic directly.')" /></div>
        @else
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="min-w-0 space-y-6 lg:col-span-2">
                    {{-- Location --}}
                    @if ($locations->count() > 1)
                        <section aria-labelledby="choose-location">
                            <h2 id="choose-location" class="mb-3 text-lg font-semibold text-slate-900">{{ __('Choose a location') }}</h2>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($locations as $item)
                                    <a href="{{ $selfRoute(['location' => $item->id, 'doctor' => null, 'date' => null]) }}" @class(['card flex items-start gap-3 p-4 transition hover:shadow-md', 'border-brand-600 ring-2 ring-brand-600' => $locationId === $item->id])>
                                        <x-icon name="pin" class="h-5 w-5 flex-none text-brand-600" />
                                        <span class="min-w-0"><span class="block font-semibold text-slate-900">{{ $item->name }}</span><span class="block text-sm text-slate-500">{{ collect([$item->address_line, $item->city])->filter()->join(', ') }}</span>@if (! empty($item->timings['text']))<span class="mt-1 block text-xs text-slate-500">{{ $item->timings['text'] }}</span>@endif</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @elseif ($location && ! empty($location->timings['text']))
                        <p class="flex items-center gap-2 text-sm text-slate-600"><x-icon name="clock" class="h-5 w-5 text-brand-600" />{{ $location->timings['text'] }}</p>
                    @endif

                    {{-- Doctors --}}
                    @if ($locationId && ! $preselected && $doctors->count() > 1)
                        <section aria-labelledby="choose-doctor">
                            <h2 id="choose-doctor" class="mb-3 text-lg font-semibold text-slate-900">{{ __('Choose a doctor') }}</h2>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($doctors as $doctor)
                                    <a href="{{ $selfRoute(['doctor' => $doctor['id'], 'date' => null]) }}" @class(['card flex items-center gap-4 p-4 transition hover:shadow-md', 'border-brand-600 ring-2 ring-brand-600' => (int) $doctorId === $doctor['id']])>
                                        <span class="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-800">{{ $doctor['initials'] }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-1 font-semibold text-slate-900"><span class="truncate">{{ $doctor['name'] }}</span>@if ($doctor['verified'])<x-icon name="badge" class="h-4 w-4 flex-none text-brand-600" /><span class="sr-only">{{ __('Verified') }}</span>@endif</span>
                                            @if ($doctor['specialty'])<span class="block truncate text-sm text-slate-600">{{ $doctor['specialty'] }}</span>@endif
                                            @if ($doctor['qualification'])<span class="block truncate text-xs text-slate-500">{{ $doctor['qualification'] }}</span>@endif
                                            @if ($doctor['fee'])<span class="mt-1 block text-sm font-semibold text-slate-900">{{ $doctor['fee'] }}</span>@endif
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($preselected && $selectedDoctor)
                        <section class="card flex items-center gap-4 p-4">
                            <span class="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-800">{{ $selectedDoctor['initials'] }}</span>
                            <div class="min-w-0">
                                <h2 class="flex items-center gap-1 font-semibold text-slate-900"><span class="truncate">{{ $selectedDoctor['name'] }}</span>@if ($selectedDoctor['verified'])<x-icon name="badge" class="h-4 w-4 flex-none text-brand-600" /><span class="sr-only">{{ __('Verified') }}</span>@endif</h2>
                                @if ($selectedDoctor['specialty'])<p class="truncate text-sm text-slate-600">{{ $selectedDoctor['specialty'] }}</p>@endif
                                @if ($selectedDoctor['qualification'])<p class="truncate text-xs text-slate-500">{{ $selectedDoctor['qualification'] }}</p>@endif
                            </div>
                        </section>
                    @endif

                    {{-- Date and time --}}
                    @if ($canShowSlots)
                        <section aria-labelledby="choose-time" class="card p-4 sm:p-5">
                            <h2 id="choose-time" class="text-lg font-semibold text-slate-900">{{ __('Choose a date and time') }}</h2>
                            <div class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-2 [scrollbar-width:thin]">
                                @foreach ($days as $day)
                                    <a href="{{ $selfRoute(['date' => $day->toDateString()]) }}" @if ($day->isSameDay($date)) aria-current="date" @endif @class(['flex w-16 flex-none flex-col items-center rounded-xl border py-2 text-center transition', 'border-brand-600 bg-brand-600 text-white' => $day->isSameDay($date), 'border-slate-200 bg-white text-slate-700 hover:border-brand-400' => ! $day->isSameDay($date)])>
                                        <span class="text-xs font-medium">{{ $day->format('D') }}</span>
                                        <span class="text-lg leading-tight font-bold">{{ $day->format('d') }}</span>
                                        <span class="text-xs">{{ $day->format('M') }}</span>
                                    </a>
                                @endforeach
                            </div>
                            @if ($open->isEmpty())
                                <p class="mt-4 rounded-lg bg-slate-50 p-4 text-center text-sm text-slate-600">{{ __('No times are available on this day. Please try another date.') }}</p>
                            @else
                                <form method="POST" action="{{ route('book.choose', $organization->slug) }}" class="mt-3 space-y-4">
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $locationId }}">
                                    <input type="hidden" name="doctor_user_id" value="{{ $doctorId }}">
                                    @foreach ($groups as $label => $group)
                                        @if ($group->isNotEmpty())
                                            <fieldset>
                                                <legend class="mb-2 text-sm font-semibold text-slate-700">{{ __($label) }}</legend>
                                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                                    @foreach ($group as $slot)
                                                        <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 transition hover:border-brand-400 has-checked:border-brand-600 has-checked:bg-brand-600 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-brand-500">
                                                            <input class="sr-only" type="radio" name="starts_at" value="{{ $slot['starts_at']->format('Y-m-d H:i') }}" required>
                                                            {{ $slot['starts_at']->format('h:i A') }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @endif
                                    @endforeach
                                    @error('starts_at')<p class="field-error">{{ $message }}</p>@enderror
                                    <button class="btn btn-primary sticky bottom-3 w-full shadow-lg sm:static sm:w-auto sm:px-10 sm:shadow-none">{{ __('Continue') }} <x-icon name="arrow" class="h-4 w-4" /></button>
                                </form>
                            @endif
                        </section>
                    @elseif ($locationId && $doctors->isNotEmpty())
                        <p class="rounded-lg bg-brand-50 p-4 text-sm text-brand-800">{{ __('Choose a doctor to see available times.') }}</p>
                    @endif
                </div>
                <div>
                    @include('booking.partials.summary', ['doctor' => $selectedDoctor, 'location' => $location])
                </div>
            </div>
        @endif
    </div>
</x-layouts.booking>
