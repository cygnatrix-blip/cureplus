<x-layouts.guest title="No organization">
    <h1 class="text-lg font-semibold text-slate-900">You are not part of any organization</h1>
    <p class="mt-2 text-sm text-slate-600">Ask your clinic for a new invitation. Your access may have been removed.</p>
    <form method="POST" action="{{ route('app.logout') }}" class="mt-4">@csrf<button class="btn btn-secondary w-full">Sign out</button></form>
</x-layouts.guest>
