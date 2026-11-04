<x-layouts.guest title="Admin sign in" portal="admin">
    <h1 class="mb-4 text-lg font-semibold text-slate-900">IT admin sign in</h1>
    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
        @csrf
        <x-field name="identifier" label="Email" type="email" autocomplete="username" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        @include('partials.captcha')
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
</x-layouts.guest>
