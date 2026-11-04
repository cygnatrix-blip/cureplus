<x-layouts.patient :title="__('Sign in')">
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:grid-cols-2">
        <div class="hidden flex-col justify-center bg-linear-to-br from-brand-800 to-brand-600 p-8 text-white md:flex">
            <h2 class="text-2xl font-bold">{{ __('Your health, in one place') }}</h2>
            <ul class="mt-5 space-y-3 text-sm">
                @foreach (['Book and manage appointments', 'See prescriptions, bills and reports', 'Choose who can see your records'] as $point)
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-5 w-5 flex-none" />{{ __($point) }}</li>
                @endforeach
            </ul>
        </div>
        <form method="POST" action="{{ route('patient.login.send') }}" class="space-y-4 p-6 sm:p-8">
            @csrf
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ __('Sign in') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Enter your email. We will send you a code; no password is needed.') }}</p>
            </div>
            <x-field name="email" :label="__('Email')" type="email" autocomplete="email" required autofocus />
            @include('partials.captcha')
            <button class="btn btn-primary w-full">{{ __('Email me a code') }}</button>
        </form>
    </div>
</x-layouts.patient>
