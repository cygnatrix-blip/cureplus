<x-layouts.patient :title="__('Enter the code')">
    <form method="POST" action="{{ route('patient.login.confirm') }}" class="card mx-auto max-w-md space-y-4 p-6 sm:p-8">
        @csrf
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-brand-700"><x-icon name="mail" class="h-6 w-6" /></div>
        <div>
            <h1 class="text-xl font-bold text-slate-900">{{ __('Check your email') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('We sent a 6-digit code to :email. Check your spam folder if you do not see it.', ['email' => $email]) }}</p>
        </div>
        <x-field name="code" :label="__('6-digit code')" inputmode="numeric" autocomplete="one-time-code" class="tracking-[0.4em]" required autofocus />
        <button class="btn btn-primary w-full">{{ __('Sign in') }}</button>
        <a class="block text-center text-sm text-brand-700 underline" href="{{ route('patient.login') }}">{{ __('Use a different email') }}</a>
    </form>
</x-layouts.patient>
