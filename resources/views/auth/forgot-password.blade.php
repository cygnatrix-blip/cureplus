<x-layouts.guest title="Forgot password">
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Reset your password</h1>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <button class="btn btn-primary w-full">Send reset link</button>
    </form>
</x-layouts.guest>
