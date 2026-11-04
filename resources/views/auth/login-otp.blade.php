<x-layouts.guest title="Sign in with a code">
    <h1 class="mb-1 text-lg font-semibold text-slate-900">Sign in with an email code</h1>
    <p class="mb-4 text-sm text-slate-600">For clinic staff. Owners and doctors sign in with a password.</p>
    <form method="POST" action="{{ route('login.otp.send') }}" class="space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        @include('partials.captcha')
        <button class="btn btn-primary w-full">Email me a code</button>
    </form>
    <p class="mt-4 text-center text-sm"><a class="text-brand-700 underline" href="{{ route('login') }}">Use a password instead</a></p>
</x-layouts.guest>
