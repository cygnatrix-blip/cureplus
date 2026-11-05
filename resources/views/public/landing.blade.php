@php
    $features = [
        ['calendar', 'Appointments and walk-ins', 'Online booking, walk-in tokens and a live queue, with double-booking blocked for every doctor.'],
        ['video', 'Video consultations', 'Patients choose a clinic visit or a video call. Take payment first if you want to.'],
        ['document', 'Digital prescriptions', 'Search the medicine catalog, then share a PDF or a secure link in English, Hindi or Bengali.'],
        ['card', 'Billing and payments', 'GST-ready bills, receipts and dues, with online payment through Razorpay or Cashfree.'],
        ['chat', 'WhatsApp, SMS and email', 'Send reminders and prescriptions from your own number, automatically or with one tap.'],
        ['users', 'Patient records', 'Visit history, vitals and allergies in one place, with a portal patients can open themselves.'],
    ];
    $steps = [
        ['Register your practice', 'Tell us about your clinic or chamber and your professional registration. It takes a few minutes.'],
        ['We verify your details', 'Our team checks every practice before patients can book, usually within 24 hours.'],
        ['Start seeing patients', 'Add your hours and team, then share your own booking website and QR code.'],
    ];
    $trust = [
        ['shield', 'Every practice is kept separate', 'Each clinic\'s patients and records are walled off from every other clinic.'],
        ['badge', 'Verified practitioners', 'Patients can only book practices and doctors our team has checked.'],
        ['check', 'Access that fits each role', 'Prescriptions stay with the treating doctor, with two-factor sign-in and an audit trail.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>@include('partials.head', ['title' => 'Clinic and doctor practice software', 'portal' => 'app', 'description' => 'Appointments, video consultations, prescriptions and billing for clinics and doctors across India.'])</head>
<body class="bg-white text-slate-700 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <img src="/icons/{{ config('cureplus.portals.app.icon') }}-192.png" alt="" class="h-9 w-9 rounded-lg">
                <span class="text-lg font-bold tracking-tight text-slate-900">{{ config('app.name') }}</span>
            </a>
            <nav class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex" aria-label="Page sections">
                <a class="hover:text-brand-700" href="#features">Features</a>
                <a class="hover:text-brand-700" href="#how-it-works">How it works</a>
                <a class="hover:text-brand-700" href="#specialties">Who it's for</a>
                <a class="hover:text-brand-700" href="#trust">Trust</a>
            </nav>
            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="btn btn-ghost">Sign in</a>
                <a href="{{ route('register') }}" class="btn btn-primary hidden sm:inline-flex">Register your practice</a>
            </div>
        </div>
    </header>

    <main id="main">
        <section class="relative overflow-hidden bg-brand-950 text-white">
            <div class="absolute -top-40 -right-32 h-[32rem] w-[32rem] rounded-full bg-brand-500/25 blur-3xl" aria-hidden="true"></div>
            <div class="absolute -bottom-48 -left-32 h-[30rem] w-[30rem] rounded-full bg-brand-400/15 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:py-24">
                <div>
                    <p class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-sm font-medium text-brand-100"><x-icon name="badge" class="h-4 w-4" />Built for clinics and doctors in India</p>
                    <h1 class="mt-6 text-4xl leading-[1.1] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">Run your whole practice from one simple place</h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-brand-100">Appointments, video consultations, prescriptions and billing, on any phone or computer. Less paperwork for you, less waiting for your patients.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-white px-7 font-semibold text-brand-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">Register your practice <x-icon name="arrow" class="h-5 w-5" /></a>
                        <a href="#features" class="inline-flex min-h-13 items-center justify-center rounded-xl border border-white/30 px-7 font-semibold text-white transition hover:bg-white/10">See what's included</a>
                    </div>
                    <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-brand-100">
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-5 w-5 text-brand-300" />Nothing to install</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-5 w-5 text-brand-300" />For solo doctors and clinics</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-5 w-5 text-brand-300" />Every practice verified</li>
                    </ul>
                </div>

                {{-- An illustration of the queue screen. The names are examples, so it is hidden from screen readers. --}}
                <figure class="relative">
                    <div class="rounded-2xl bg-white p-5 text-slate-700 shadow-2xl ring-1 ring-black/5 sm:p-6" aria-hidden="true">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div><p class="font-bold text-slate-900">Today's queue</p><p class="text-sm text-slate-500">Dr. Example · Main branch</p></div>
                            <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">3 waiting</span>
                        </div>
                        <ul class="mt-2 divide-y divide-slate-100">
                            @foreach ([[12, 'Patient A', '10:15', 'With doctor', 'bg-brand-600 text-white', 'clinic'], [13, 'Patient B', '10:30', 'Video call', 'bg-sky-100 text-sky-800', 'video'], [14, 'Patient C', 'Walk-in', 'Waiting', 'bg-amber-100 text-amber-800', 'clinic'], [15, 'Patient D', '11:00', 'Booked', 'bg-slate-100 text-slate-700', 'clinic']] as [$token, $name, $time, $status, $tone, $mode])
                                <li class="flex items-center gap-3 py-3">
                                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-slate-900 font-semibold text-white">{{ $token }}</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate font-medium text-slate-900">{{ $name }}</span><span class="flex items-center gap-1 text-xs text-slate-500">@if ($mode === 'video')<x-icon name="video" class="h-3.5 w-3.5" />@else<x-icon name="clock" class="h-3.5 w-3.5" />@endif{{ $time }}</span></span>
                                    <span class="flex-none rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ $status }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <figcaption class="mt-3 text-center text-xs text-brand-200">Illustration of the queue screen, with example data</figcaption>
                </figure>
            </div>
        </section>

        <section id="features" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">Everything in one place</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-balance text-slate-900 sm:text-4xl">From the first booking to the final bill</h2>
                </div>
                <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as [$icon, $title, $text])
                        <article class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><x-icon :name="$icon" class="h-6 w-6" /></span>
                            <h3 class="mt-5 text-lg font-bold text-slate-900">{{ $title }}</h3>
                            <p class="mt-2 leading-relaxed text-slate-600">{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-slate-50 py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16">
                <div>
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">Your own online presence</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-balance text-slate-900 sm:text-4xl">Every practice gets its own website</h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600">Add your logo, colours, doctors and timings. Patients book on your page, with your name on it, from a link or a QR code on your wall.</p>
                </div>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ([['pin', 'Several branches'], ['users', 'Doctors and reception staff'], ['bell', 'Automatic reminders'], ['phone', 'Works like an app on phones']] as [$icon, $label])
                        <li class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 font-medium text-slate-800"><x-icon :name="$icon" class="h-5 w-5 flex-none text-brand-600" />{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section id="how-it-works" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">How it works</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-balance text-slate-900 sm:text-4xl">Three steps to get started</h2>
                </div>
                <ol class="mt-12 grid gap-5 lg:grid-cols-3">
                    @foreach ($steps as $index => [$title, $text])
                        <li class="rounded-2xl border border-slate-200 p-6">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-600 font-bold text-white">{{ $index + 1 }}</span>
                            <h3 class="mt-5 text-lg font-bold text-slate-900">{{ $title }}</h3>
                            <p class="mt-2 leading-relaxed text-slate-600">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="specialties" class="scroll-mt-20 bg-slate-50 py-16 sm:py-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">Who it's for</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-balance text-slate-900 sm:text-4xl">Made for every kind of practitioner</h2>
                    <p class="mt-4 text-lg text-slate-600">Allopathy, AYUSH and allied health, with medicines to match.</p>
                </div>
                <dl class="mt-12 space-y-6">
                    @foreach (\App\Support\Specialties::bySystem() as $system => $specialties)
                        @if (count($specialties) > 1)
                            <div>
                                <dt class="font-bold text-slate-900">{{ $system }}</dt>
                                <dd class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($specialties as $specialty)<span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700">{{ $specialty }}</span>@endforeach
                                </dd>
                            </div>
                        @endif
                    @endforeach
                    <div>
                        <dt class="font-bold text-slate-900">AYUSH</dt>
                        <dd class="mt-3 flex flex-wrap gap-2">
                            @foreach (\App\Support\Specialties::bySystem() as $system => $specialties)
                                @if (count($specialties) === 1)<span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700">{{ $system }}</span>@endif
                            @endforeach
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <section id="trust" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold tracking-wider text-brand-700 uppercase">Built on trust</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-balance text-slate-900 sm:text-4xl">Patient information deserves care</h2>
                </div>
                <div class="mt-12 grid gap-8 md:grid-cols-3">
                    @foreach ($trust as [$icon, $title, $text])
                        <div class="text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-700"><x-icon :name="$icon" class="h-7 w-7" /></span>
                            <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $title }}</h3>
                            <p class="mt-2 leading-relaxed text-slate-600">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="px-4 pb-16 sm:px-6 sm:pb-24">
            <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-brand-800 px-6 py-14 text-center text-white sm:py-16">
                <div class="absolute -top-24 -right-20 h-72 w-72 rounded-full bg-brand-500/30 blur-3xl" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">Ready to bring your practice online?</h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-brand-100">Register today. We check every practice, usually within 24 hours.</p>
                    <a href="{{ route('register') }}" class="mt-8 inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-white px-7 font-semibold text-brand-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">Register your practice <x-icon name="arrow" class="h-5 w-5" /></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-slate-600 sm:flex-row sm:px-6">
            <p>© {{ now()->year }} {{ config('app.name') }}</p>
            <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2" aria-label="Footer">
                <a class="hover:text-brand-700" href="{{ route('patient.login') }}">Patient sign-in</a>
                <a class="hover:text-brand-700" href="{{ route('privacy') }}">Privacy</a>
                <a class="hover:text-brand-700" href="{{ route('terms') }}">Terms</a>
            </nav>
        </div>
    </footer>
    @include('partials.pwa')
</body>
</html>
