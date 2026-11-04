<x-layouts.guest title="Verify your account">
    @php($user = auth('web')->user())
    <h1 class="mb-1 text-lg font-semibold text-slate-900">Verify your account</h1>
    <p class="mb-4 text-sm text-slate-600">Confirm your email to continue.</p>
        <form method="POST" action="{{ route('app.verification.send') }}">@csrf<button class="btn btn-secondary w-full">Email me a verification link</button></form>
    <form method="POST" action="{{ route('app.logout') }}" class="mt-3">@csrf<button class="btn btn-ghost w-full">Sign out</button></form>
</x-layouts.guest>
