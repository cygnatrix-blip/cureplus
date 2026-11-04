<x-layouts.guest title="Choose a new password">
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" autocomplete="email" required />
        <x-field name="password" label="New password" type="password" autocomplete="new-password" required />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <button class="btn btn-primary w-full">Update password</button>
    </form>
</x-layouts.guest>
