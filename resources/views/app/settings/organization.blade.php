<x-layouts.app title="Organization details">
    <form method="POST" action="{{ route('app.settings.organization.update') }}" class="card card-body mx-auto max-w-2xl space-y-4">
        @csrf @method('PUT')
        <x-field name="name" label="Name" :value="$organization->name" required />
        <x-field name="gstin" label="GSTIN (optional)" :value="$organization->gstin" hint="Printed on tax invoices." />
        <p class="text-sm text-slate-600">Your colours, logo and wording are under <a class="text-brand-700 underline" href="{{ route('app.settings.design') }}">Patient page design</a>.</p>
        <p class="text-sm text-slate-600">Your booking link: <span class="font-mono">{{ route('booking.show', $organization->slug) }}</span>. It does not change, so printed QR codes keep working.</p>
        <div class="text-right"><button class="btn btn-primary">Save</button></div>
    </form>
</x-layouts.app>
