<x-layouts.guest title="Terms of service" wide>
    <h1 class="text-xl font-semibold text-slate-900">Terms of service</h1>
    @if ($text)
        <p class="mt-4 text-sm whitespace-pre-line text-slate-700">{{ $text }}</p>
    @else
    <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Draft. This text must be reviewed by legal counsel before launch.</p>
    <div class="mt-4 space-y-3 text-sm text-slate-700">
        <p>By registering, you confirm that you are authorised to act for your practice and that the information you give is accurate.</p>
        <p>You are responsible for the clinical content you record and for keeping your sign-in details secure.</p>
    </div>
    @endif
</x-layouts.guest>
