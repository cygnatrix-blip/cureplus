<x-layouts.guest title="Enter your code">
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Enter your code</h1>
    <form method="POST" action="{{ route('login.otp.confirm') }}" class="space-y-4">
        @csrf
        <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" required autofocus />
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
</x-layouts.guest>
