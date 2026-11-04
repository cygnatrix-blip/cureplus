@php($status = $organization->status)
<x-layouts.app title="Verification">
    <x-page-header title="Verification" subtitle="We check every practice before patients can book, so patients can trust who they see.">
        <x-org-status :status="$status" />
    </x-page-header>

    @if ($status === \App\Enums\OrganizationStatus::Approved)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-900" role="status">Your practice is verified. Everything is open.</div>
    @endif

    <div class="grid items-start gap-4 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            @if ($isDoctor)
                <form method="POST" action="{{ route('app.verification.registration') }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="font-semibold text-slate-900">Your medical registration</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="registration_number" label="Registration number" :value="$profile?->registration_number" required />
                        <x-field name="registration_council" label="State medical council" :value="$profile?->registration_council" list="councils" required />
                    </div>
                    <datalist id="councils">@foreach ($councils as $council)<option value="{{ $council }}"></option>@endforeach</datalist>
                    <button class="btn btn-primary">Save</button>
                </form>
            @endif

            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">Documents</h2>
                @forelse ($documents as $document)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                        <span class="min-w-0"><span class="block truncate font-medium text-slate-900">{{ $document->original_name }}</span><span class="block text-xs text-slate-500">{{ $document->typeLabel() }} · {{ $document->created_at?->format('d M Y') }}</span></span>
                        <span class="flex gap-2">
                            <a class="btn btn-sm btn-secondary" href="{{ route('app.verification.documents.show', $document) }}" target="_blank" rel="noopener">Open</a>
                            @unless ($organization->isActive())
                                <form method="POST" action="{{ route('app.verification.documents.destroy', $document) }}" data-confirm="Remove this document?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Remove</button></form>
                            @endunless
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">Nothing uploaded yet.</p>
                @endforelse

                @unless ($organization->isActive())
                    <form method="POST" action="{{ route('app.verification.documents.store') }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-100 pt-3">
                        @csrf
                        <div>
                            <label class="label" for="type">What is this?</label>
                            <select class="input" id="type" name="type">
                                @foreach ($documentTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $organization->isSolo() ? 'doctor_registration' : 'clinic_proof') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="document">File</label>
                            <input class="input" id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                            <p class="hint">PDF, JPG or PNG, up to 5 MB. A clear phone photo is fine.</p>
                            @error('document')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn btn-primary">Upload</button>
                    </form>
                @endunless
            </section>
        </div>

        <aside class="space-y-4">
            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">What we need</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($requirements as $requirement)
                        <li class="flex items-start gap-2">
                            <x-icon name="check" @class(['h-5 w-5 flex-none', 'text-emerald-600' => $requirement['done'], 'text-slate-300' => ! $requirement['done']]) />
                            <span>{{ $requirement['label'] }}<span class="sr-only">: {{ $requirement['done'] ? 'done' : 'still needed' }}</span></span>
                        </li>
                    @endforeach
                </ul>
                @if ($status === \App\Enums\OrganizationStatus::Rejected)
                    <p class="rounded-lg bg-rose-50 p-3 text-sm text-rose-900"><strong>Why it was not approved:</strong> {{ $organization->rejection_reason }}</p>
                    <form method="POST" action="{{ route('app.verification.resubmit') }}">@csrf<button class="btn btn-primary w-full">Send for review again</button></form>
                @elseif ($status === \App\Enums\OrganizationStatus::PendingVerification)
                    <p class="text-sm text-slate-600">Our team reviews this usually within 24 hours. We will email you the result.</p>
                @endif
            </section>

            @unless ($organization->isActive())
                <section class="card card-body space-y-2">
                    <h2 class="font-semibold text-slate-900">While you wait</h2>
                    <p class="text-sm text-slate-600">Add your branches, staff and fees now. You can try appointments and consultations on sample patients, which are removed when you are approved.</p>
                    <form method="POST" action="{{ route('app.verification.sample-data') }}">@csrf<button class="btn btn-secondary w-full">Add sample patients</button></form>
                    <p class="hint">Opens after approval: booking links, real patients, bills, prescription printing, online payments, SMS and WhatsApp.</p>
                </section>
            @endunless
        </aside>
    </div>
</x-layouts.app>
