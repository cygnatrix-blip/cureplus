<x-layouts.guest title="Sign in">
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Sign in</h1>
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <x-field name="identifier" label="Email or mobile" autocomplete="username" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        @include('partials.captcha')
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
    <div class="mt-4 flex flex-wrap justify-between gap-2 text-sm">
        <a class="text-brand-700 underline" href="{{ route('login.otp') }}">Sign in with an email code</a>
        <a class="text-brand-700 underline" href="{{ route('password.request') }}">Forgot password?</a>
    </div>
    <p class="mt-4 text-center text-sm text-slate-600">New here? <a class="text-brand-700 underline" href="{{ route('register') }}">Register your practice</a></p>
</x-layouts.guest>
