<x-layouts.guest title="Your invitation">
    <h1 class="mb-1 text-lg font-semibold text-slate-900">Join {{ $organization->name }}</h1>
    <p class="mb-4 text-sm text-slate-600">You have been invited as: {{ implode(', ', array_map('ucfirst', $invitation->role_keys)) }}.</p>
    <form method="POST" action="{{ route('invitations.accept', $token) }}" class="space-y-4">
        @csrf
        @unless ($existing)
            <x-field name="name" label="Your full name" :value="$invitation->name" autocomplete="name" required />
            <x-field name="password" label="Choose a password" type="password" autocomplete="new-password" hint="At least 10 characters, with letters and numbers." required />
            <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        @endunless
        <button class="btn btn-primary w-full">Accept invitation</button>
    </form>
</x-layouts.guest>
