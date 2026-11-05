@php
    $branding = $organization->branding ?? [];
    $tagline = $branding['tagline'] ?? null;
    $about = $branding['about'] ?? null;
    $highlights = $branding['highlights'] ?? [];
    $services = $branding['services'] ?? [];
    $faqs = $branding['faqs'] ?? [];
    $gallery = $branding['gallery'] ?? [];
    $phone = $branding['contact_phone'] ?? null;
    $contactEmail = $branding['contact_email'] ?? null;
    $established = $branding['established_year'] ?? null;
    $telLink = fn (?string $number) => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $number);
    $bookUrl = route('booking.show', $organization->slug);
    $hours = $locations->map(fn ($location) => $location->timings['text'] ?? null)->filter()->first();
    $stats = array_values(array_filter([
        $doctors->isNotEmpty() ? ['value' => $doctors->count(), 'label' => __('Doctors')] : null,
        $locations->isNotEmpty() ? ['value' => $locations->count(), 'label' => __('Branches')] : null,
        $established ? ['value' => max(1, now()->year - (int) $established).'+', 'label' => __('Years of care')] : null,
        $hours ? ['value' => $hours, 'label' => __('Opening hours'), 'small' => true] : null,
    ]));
    $nav = array_filter([
        '#doctors' => $doctors->isNotEmpty() ? __('Doctors') : null,
        '#services' => $services ? __('Services') : null,
        '#about' => $about || $gallery ? __('About') : null,
        '#visit' => $locations->isNotEmpty() ? __('Visit us') : null,
        '#questions' => $faqs ? __('Questions') : null,
    ]);
@endphp
<x-layouts.clinic :organization="$organization">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur-lg">
        <div class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
            <a href="{{ route('clinic.home', $organization->slug) }}" class="flex min-w-0 items-center gap-3">
                @if ($organization->icon_192_path)
                    <img src="{{ route('brand.icon', [$organization->slug, 192]) }}" alt="" class="h-11 w-11 flex-none rounded-xl object-contain">
                @else
                    <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-800 text-lg font-bold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($organization->name, 0, 1)) }}</span>
                @endif
                <span class="truncate text-lg font-bold tracking-tight text-slate-900">{{ $organization->name }}</span>
            </a>
            <nav class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex" aria-label="{{ __('Quick links') }}">
                @foreach ($nav as $anchor => $label)
                    <a class="transition hover:text-brand-700" href="{{ $anchor }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex flex-none items-center gap-2">
                @include('partials.language')
                @auth('patient')
                    <a class="btn btn-sm btn-ghost hidden sm:inline-flex" href="{{ route('patient.home') }}">{{ __('My account') }}</a>
                @else
                    <a class="btn btn-sm btn-ghost hidden sm:inline-flex" href="{{ route('patient.login') }}">{{ __('Sign in') }}</a>
                @endauth
                <a class="btn btn-primary hidden lg:inline-flex" href="{{ $bookUrl }}">{{ __('Book appointment') }}</a>
            </div>
        </div>
    </header>

    <main>
        <section class="relative isolate overflow-hidden bg-linear-to-br from-brand-950 via-brand-800 to-brand-600 text-white">
            @if ($organization->cover_image_path)
                <img src="{{ route('brand.cover', $organization->slug) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover">
                <div class="absolute inset-0 -z-10 bg-linear-to-r from-brand-950/95 via-brand-900/80 to-brand-900/30"></div>
            @else
                <div class="absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -bottom-32 left-1/3 -z-10 h-96 w-96 rounded-full bg-accent-500/30 blur-3xl"></div>
            @endif
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 pt-14 pb-24 sm:px-6 lg:grid-cols-5 lg:pt-20 lg:pb-32">
                <div class="lg:col-span-3">
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium backdrop-blur"><x-icon name="badge" class="h-4 w-4" />{{ __('Verified practice') }}</p>
                    <h1 class="mt-5 text-4xl leading-tight font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $tagline ?: __('Book your appointment at :clinic', ['clinic' => $organization->name]) }}</h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/85">{{ \Illuminate\Support\Str::limit($about ?: __('Choose a doctor and a time that suits you. It takes about a minute.'), 180) }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a class="inline-flex min-h-13 items-center gap-2 rounded-xl bg-white px-7 font-semibold text-brand-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl" href="{{ $bookUrl }}">{{ __('Book appointment') }}<x-icon name="arrow" class="h-4 w-4" /></a>
                        @if ($doctors->contains('offers_video', true))
                            <a class="inline-flex min-h-13 items-center gap-2 rounded-xl border border-white/40 px-6 font-semibold text-white transition hover:bg-white/10" href="{{ route('booking.show', [$organization->slug, 'mode' => 'online']) }}"><x-icon name="video" class="h-5 w-5" />{{ __('Book a video call') }}</a>
                        @endif
                        @if ($phone)
                            <a class="inline-flex min-h-13 items-center gap-2 rounded-xl border border-white/40 px-6 font-semibold text-white transition hover:bg-white/10" href="{{ $telLink($phone) }}"><x-icon name="phone" class="h-5 w-5" />{{ $phone }}</a>
                        @endif
                    </div>
                    @if ($highlights)
                        <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-white/90">
                            @foreach ($highlights as $highlight)
                                <li class="flex items-center gap-2"><x-icon name="check" class="h-5 w-5 text-accent-100" />{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($doctors->isNotEmpty())
                    <aside class="hidden rounded-3xl bg-white p-6 text-slate-700 shadow-2xl lg:col-span-2 lg:block">
                        <h2 class="text-xl font-bold text-slate-900">{{ __('Book in about a minute') }}</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($doctors->take(3) as $doctor)
                                <li>
                                    <a class="flex items-center gap-3 rounded-xl p-2 transition hover:bg-brand-50" href="{{ route('booking.show', [$organization->slug, 'doctor' => $doctor['id']]) }}">
                                        @if ($doctor['photo'])
                                            <img src="{{ $doctor['photo'] }}" alt="" class="h-12 w-12 flex-none rounded-full object-cover">
                                        @else
                                            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">{{ $doctor['initials'] }}</span>
                                        @endif
                                        <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-slate-900">{{ $doctor['name'] }}</span>@if ($doctor['specialty'])<span class="block truncate text-sm text-slate-500">{{ $doctor['specialty'] }}</span>@endif</span>
                                        <x-icon name="arrow" class="h-4 w-4 flex-none text-brand-600" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a class="btn btn-primary mt-4 w-full" href="{{ $bookUrl }}">{{ __('See all available times') }}</a>
                    </aside>
                @endif
            </div>
        </section>

        @if ($stats)
            <section class="relative z-10 mx-auto -mt-12 max-w-6xl px-4 sm:px-6" aria-label="{{ __('At a glance') }}">
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-slate-200 shadow-xl ring-1 ring-slate-200 lg:grid-flow-col lg:auto-cols-fr lg:grid-cols-none">
                    @foreach ($stats as $stat)
                        <div class="flex flex-col-reverse bg-white p-5 text-center odd:last:col-span-2 lg:odd:last:col-span-1">
                            <dt class="mt-1 text-sm text-slate-500">{{ $stat['label'] }}</dt>
                            <dd @class(['font-extrabold text-brand-700', 'text-sm leading-snug' => $stat['small'] ?? false, 'text-3xl' => ! ($stat['small'] ?? false)])>{{ $stat['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($doctors->isNotEmpty())
            <section id="doctors" class="scroll-mt-20 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">{{ __('Our doctors') }}</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Meet the doctors who will look after you') }}</h2>
                    <div @class(['mt-10 grid gap-6', 'max-w-3xl' => $doctors->count() === 1, 'sm:grid-cols-2 lg:grid-cols-3' => $doctors->count() > 1])>
                        @foreach ($doctors as $doctor)
                            <article @class(['group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-xl', 'sm:flex-row' => $doctors->count() === 1])>
                                <div @class(['flex h-52 flex-none items-center justify-center overflow-hidden bg-linear-to-br from-brand-50 to-brand-100', 'sm:h-auto sm:w-64' => $doctors->count() === 1])>
                                    @if ($doctor['photo'])
                                        <img src="{{ $doctor['photo'] }}" alt="{{ $doctor['name'] }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                    @else
                                        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white text-3xl font-bold text-brand-700 shadow-sm">{{ $doctor['initials'] }}</span>
                                    @endif
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col p-5">
                                    <h3 class="flex items-center gap-1.5 text-lg font-bold text-slate-900">{{ $doctor['name'] }}@if ($doctor['verified'])<x-icon name="badge" class="h-5 w-5 flex-none text-brand-600" /><span class="sr-only">{{ __('Verified') }}</span>@endif</h3>
                                    @if ($doctor['specialty'])<p class="mt-1 font-medium text-brand-700">{{ $doctor['specialty'] }}</p>@endif
                                    @if ($doctor['qualification'])<p class="mt-1 text-sm text-slate-500">{{ $doctor['qualification'] }}</p>@endif
                                    @if ($doctor['offers_video'])<p class="mt-2 flex items-center gap-1 text-sm font-medium text-brand-700"><x-icon name="video" class="h-4 w-4" />{{ __('Video calls available') }}</p>@endif
                                    <div class="mt-auto flex items-center justify-between gap-3 pt-5">
                                        @if ($doctor['fee'])<p class="text-sm text-slate-500">{{ __('Consultation fee') }}<span class="block text-base font-bold text-slate-900">{{ $doctor['fee'] }}</span></p>@else<span></span>@endif
                                        <a class="btn btn-primary" href="{{ route('booking.show', [$organization->slug, 'doctor' => $doctor['id']]) }}">{{ __('Book') }}</a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($services)
            <section id="services" class="scroll-mt-20 bg-slate-50 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">{{ __('Services') }}</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('What we can help with') }}</h2>
                    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($services as $service)
                            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-100 text-brand-700"><x-icon name="check" class="h-6 w-6" /></span>
                                <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $service['title'] }}</h3>
                                @if (! empty($service['text']))<p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $service['text'] }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($about || $gallery)
            <section id="about" class="scroll-mt-20 py-20">
                <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">{{ __('About') }}</p>
                        <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $organization->name }}</h2>
                        @if ($about)<p class="mt-5 text-lg leading-relaxed whitespace-pre-line text-slate-600">{{ $about }}</p>@endif
                    </div>
                    @if ($gallery)
                        <div class="grid grid-cols-2 gap-3" aria-label="{{ __('Clinic photos') }}">
                            @foreach ($gallery as $index => $path)
                                <img src="{{ route('brand.gallery', [$organization->slug, $index]) }}" alt="" loading="lazy" @class(['h-44 w-full rounded-2xl object-cover sm:h-56', 'col-span-2' => $loop->first && $loop->count % 2 === 1])>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($locations->isNotEmpty())
            <section id="visit" class="scroll-mt-20 bg-slate-50 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">{{ __('Visit us') }}</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Find us') }}</h2>
                    <div class="mt-10 grid gap-5 md:grid-cols-2">
                        @foreach ($locations as $location)
                            @php($address = collect([$location->address_line, $location->city, $location->state, $location->pincode])->filter()->join(', '))
                            @php($locationPhone = $location->phone ?: $phone)
                            <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                <h3 class="text-xl font-bold text-slate-900">{{ $location->name }}</h3>
                                @if ($address)<p class="mt-3 flex items-start gap-3 text-slate-600"><x-icon name="pin" class="h-5 w-5 flex-none text-brand-600" />{{ $address }}</p>@endif
                                @if (! empty($location->timings['text']))<p class="mt-2 flex items-start gap-3 text-slate-600"><x-icon name="clock" class="h-5 w-5 flex-none text-brand-600" />{{ $location->timings['text'] }}</p>@endif
                                <div class="mt-5 flex flex-wrap gap-2">
                                    <a class="btn btn-primary" href="{{ route('booking.show', [$organization->slug, 'location' => $location->id]) }}">{{ __('Book here') }}</a>
                                    @if ($locationPhone)<a class="btn btn-secondary" href="{{ $telLink($locationPhone) }}"><x-icon name="phone" class="h-4 w-4" />{{ __('Call') }}</a>@endif
                                    @if ($address)<a class="btn btn-secondary" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($organization->name.', '.$address) }}" target="_blank" rel="noopener noreferrer"><x-icon name="pin" class="h-4 w-4" />{{ __('Get directions') }}</a>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($faqs)
            <section id="questions" class="scroll-mt-20 py-20">
                <div class="mx-auto max-w-3xl px-4 sm:px-6">
                    <p class="text-center text-sm font-semibold tracking-wider text-brand-700 uppercase">{{ __('Questions') }}</p>
                    <h2 class="mt-2 text-center text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Common questions') }}</h2>
                    <div class="mt-10 space-y-3">
                        @foreach ($faqs as $faq)
                            <details class="group rounded-2xl border border-slate-200 bg-white p-5 open:shadow-md">
                                <summary class="cursor-pointer list-none font-semibold text-slate-900 marker:hidden"><span class="flex items-center justify-between gap-4">{{ $faq['question'] }}<x-icon name="plus" class="h-5 w-5 flex-none text-brand-600 transition group-open:rotate-45" /></span></summary>
                                <p class="mt-3 leading-relaxed whitespace-pre-line text-slate-600">{{ $faq['answer'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="bg-linear-to-br from-brand-800 to-brand-600 py-16 text-white">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-6 px-4 text-center sm:px-6">
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('Ready when you are') }}</h2>
                <p class="max-w-xl text-lg text-white/85">{{ __('Choose a doctor and a time that suits you. It takes about a minute.') }}</p>
                <a class="inline-flex min-h-13 items-center gap-2 rounded-xl bg-white px-8 font-semibold text-brand-800 shadow-lg transition hover:-translate-y-0.5" href="{{ $bookUrl }}">{{ __('Book appointment') }}<x-icon name="arrow" class="h-4 w-4" /></a>
            </div>
        </section>
    </main>

    <footer class="bg-slate-950 text-slate-400">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3">
            <div>
                <p class="text-lg font-bold text-white">{{ $organization->name }}</p>
                @if ($tagline)<p class="mt-2 text-sm">{{ $tagline }}</p>@endif
            </div>
            <div>
                <p class="font-semibold text-white">{{ __('Contact') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @if ($phone)<li><a class="flex items-center gap-2 hover:text-white" href="{{ $telLink($phone) }}"><x-icon name="phone" class="h-4 w-4" />{{ $phone }}</a></li>@endif
                    @if ($contactEmail)<li><a class="flex items-center gap-2 hover:text-white" href="mailto:{{ $contactEmail }}"><x-icon name="mail" class="h-4 w-4" />{{ $contactEmail }}</a></li>@endif
                    <li><a class="hover:text-white" href="{{ route('patient.login') }}">{{ __('Sign in') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-white">{{ __('Quick links') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a class="hover:text-white" href="{{ $bookUrl }}">{{ __('Book appointment') }}</a></li>
                    <li><a class="hover:text-white" href="{{ route('privacy') }}">{{ __('Privacy') }}</a></li>
                    <li><a class="hover:text-white" href="{{ route('terms') }}">{{ __('Terms') }}</a></li>
                </ul>
                <div class="mt-4">@include('partials.install-button', ['class' => 'btn btn-sm btn-secondary'])</div>
            </div>
        </div>
        <p class="border-t border-slate-800 px-4 py-4 text-center text-xs">{{ __('Powered by') }} {{ config('app.name') }}</p>
    </footer>

    <div class="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t border-slate-200 bg-white/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden">
        @if ($phone)<a class="btn btn-secondary flex-none" href="{{ $telLink($phone) }}" aria-label="{{ __('Call') }}"><x-icon name="phone" /></a>@endif
        <a class="btn btn-primary flex-1" href="{{ $bookUrl }}">{{ __('Book appointment') }}</a>
    </div>
</x-layouts.clinic>
