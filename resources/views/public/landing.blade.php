<x-layouts.guest title="Clinic management">
    <h1 class="text-xl font-semibold text-slate-900">OPD management for clinics and doctors</h1>
    <p class="mt-2 text-sm text-slate-600">Appointments, consultations, prescriptions and billing in one place, on any device.</p>
    <div class="mt-6 grid gap-2 sm:grid-cols-2">
        <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
        <a href="{{ route('register') }}" class="btn btn-secondary">Register your practice</a>
    </div>
    <p class="mt-6 text-center text-xs text-slate-500"><a class="underline" href="{{ route('privacy') }}">Privacy</a> · <a class="underline" href="{{ route('terms') }}">Terms</a></p>
</x-layouts.guest>
