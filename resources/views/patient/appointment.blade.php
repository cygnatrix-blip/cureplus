<x-layouts.patient :title="__('Appointments')">
    <div class="mx-auto max-w-3xl space-y-4">
        @if (session('booked'))
            <section class="rounded-2xl bg-emerald-50 p-5 text-center" role="status">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-600 text-white"><x-icon name="check" class="h-8 w-8" /></span>
                <h1 class="mt-3 text-xl font-bold text-emerald-900">{{ $appointment->isAwaitingPayment() ? __('Your time is held. Please pay to confirm it.') : __('Your appointment is booked.') }}</h1>
                @if (! $appointment->isAwaitingPayment() && $appointment->patient?->email && app(\App\Services\OrganizationMail::class)->hasOwnEmail((int) $appointment->organization_id))<p class="text-sm text-emerald-800">{{ __('The details are on their way to your email.') }}</p>@endif
            </section>
        @endif
        <article class="card overflow-hidden">
            <header class="flex items-center justify-between gap-3 bg-linear-to-br from-brand-800 to-brand-600 p-5 text-white">
                <div class="min-w-0">
                    <p class="text-sm text-white/80">{{ $appointment->organization?->name }}</p>
                    <p class="text-xl font-bold">{{ $appointment->scheduled_on->format('l, d M Y') }}</p>
                    @if ($appointment->starts_at)<p class="font-medium">{{ $appointment->starts_at->format('h:i A') }}</p>@endif
                </div>
                <div class="flex-none rounded-xl bg-white p-3 text-center text-brand-800">
                    <p class="text-xs font-semibold uppercase">{{ __('Token') }}</p>
                    <p class="text-3xl leading-tight font-bold">{{ $appointment->token_number }}</p>
                </div>
            </header>
            <dl class="grid gap-4 p-5 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ __('Doctor') }}</dt><dd class="font-semibold text-slate-900">{{ $appointment->doctor?->name }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Patient') }}</dt><dd class="font-semibold text-slate-900">{{ $appointment->patient?->name }}</dd></div>
                @if ($appointment->isOnline())
                    <div><dt class="text-slate-500">{{ __('Where') }}</dt><dd class="font-semibold text-slate-900">{{ __('Video call') }}</dd></div>
                @else
                    <div><dt class="text-slate-500">{{ __('Location') }}</dt><dd class="font-semibold text-slate-900">{{ $appointment->location?->name }}</dd><dd class="text-slate-500">{{ collect([$appointment->location?->address_line, $appointment->location?->city])->filter()->join(', ') }}</dd></div>
                @endif
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd><span class="badge badge-slate">{{ __($appointment->status->label()) }}</span></dd></div>
            </dl>
        </article>
        @if ($appointment->isAwaitingPayment())
            <section class="card space-y-3 border-amber-300 bg-amber-50 p-5" role="status">
                <h2 class="font-semibold text-amber-950">{{ __('Pay to confirm your video call') }}</h2>
                <p class="text-sm text-amber-900">{{ __('We are holding this time for you until :time. You will get the link to join as soon as your payment reaches us.', ['time' => $appointment->pay_by->format('h:i A')]) }}</p>
                @if ($canPay)<form method="POST" action="{{ route('patient.appointments.pay', $appointment->id) }}">@csrf<button class="btn btn-primary w-full"><x-icon name="card" />{{ __('Pay now') }}</button></form>@endif
            </section>
        @endif
        @if ($appointment->canJoinVideo())
            <section class="card space-y-2 p-5">
                <a class="btn btn-primary w-full" href="{{ $appointment->video_link }}" target="_blank" rel="noopener noreferrer"><x-icon name="video" />{{ __('Join video call') }}</a>
                <p class="text-sm text-slate-600">{{ __('Join at your appointment time, and allow your camera and microphone. Ask to join, then stay on the page until the doctor lets you in. Your phone may offer to open the Google Meet app, which is free.') }}</p>
            </section>
        @endif
        @if ($canPay && ! $appointment->isAwaitingPayment())
            <form method="POST" action="{{ route('patient.appointments.pay', $appointment->id) }}">@csrf<button class="btn btn-primary w-full"><x-icon name="card" />{{ __('Pay online') }}</button></form>
        @endif
        @if ($canChange)
            <section class="card p-5">
                <h2 class="font-semibold text-slate-900">{{ __('Move to a new time') }}</h2>
                <form method="GET" class="mt-2"><input class="input" type="date" name="date" value="{{ $date->toDateString() }}" min="{{ today()->toDateString() }}" data-auto-submit aria-label="{{ __('Date') }}"></form>
                <form method="POST" action="{{ route('patient.appointments.reschedule', $appointment->id) }}" class="mt-3 space-y-3">
                    @csrf
                    @php($open = collect($slots)->where('available', true))
                    @if ($open->isEmpty())
                        <p class="text-sm text-slate-600">{{ __('No times are available on this day. Please try another date.') }}</p>
                    @else
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                            @foreach ($open as $slot)
                                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-slate-300 text-sm font-medium has-checked:border-brand-600 has-checked:bg-brand-600 has-checked:text-white">
                                    <input class="sr-only" type="radio" name="starts_at" value="{{ $slot['starts_at']->format('Y-m-d H:i') }}" required>
                                    {{ $slot['starts_at']->format('h:i A') }}
                                </label>
                            @endforeach
                        </div>
                        <button class="btn btn-secondary w-full sm:w-auto">{{ __('Move appointment') }}</button>
                    @endif
                    @error('starts_at')<p class="field-error">{{ $message }}</p>@enderror
                </form>
            </section>
            <form method="POST" action="{{ route('patient.appointments.cancel', $appointment->id) }}" class="card space-y-2 p-5" data-confirm="{{ __('Cancel') }}?">
                @csrf
                <h2 class="font-semibold text-slate-900">{{ __('Cancel this appointment') }}</h2>
                <x-field name="reason" :label="__('Reason')" required />
                <button class="btn btn-danger">{{ __('Cancel') }}</button>
            </form>
        @endif
    </div>
</x-layouts.patient>
