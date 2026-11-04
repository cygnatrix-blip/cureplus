<x-layouts.guest title="Two-factor sign-in">
    <h1 class="mb-1 text-lg font-semibold text-slate-900">Confirm it's you</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $usesTotp ? 'Enter the code from your authenticator app.' : 'Enter the code we just sent you.' }} A recovery code also works here.</p>
    <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
        @csrf
        <x-field name="code" label="Code" autocomplete="one-time-code" required autofocus />
        <button class="btn btn-primary w-full">Continue</button>
    </form>
    @unless ($usesTotp)
        <form method="POST" action="{{ route('two-factor.resend') }}" class="mt-3">@csrf<button class="btn btn-ghost w-full">Send a new code</button></form>
    @endunless
</x-layouts.guest>
