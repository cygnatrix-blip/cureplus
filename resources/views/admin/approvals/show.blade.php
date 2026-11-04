<x-layouts.admin :title="$organization->name">
    <x-page-header :title="$organization->name" :subtitle="$organization->type->label().' · signed up '.$organization->created_at?->format('d M Y')">
        <x-org-status :status="$organization->status" />
    </x-page-header>

    <div class="grid items-start gap-4 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Documents</h2></div>
                @forelse ($documents as $document)
                    <div class="border-b border-slate-100 p-4 last:border-b-0 sm:px-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="min-w-0"><span class="block truncate font-medium text-slate-900">{{ $document->original_name }}</span><span class="block text-xs text-slate-500">{{ $document->typeLabel() }} · {{ $document->created_at?->format('d M Y, h:i A') }}</span></span>
                            <a class="btn btn-sm btn-secondary" href="{{ route('admin.approvals.document', [$organization, $document->id]) }}" target="_blank" rel="noopener">{{ $document->isImage() ? 'Open full size' : 'Download PDF' }}</a>
                        </div>
                        @if ($document->isImage())
                            <img src="{{ route('admin.approvals.document', [$organization, $document->id]) }}" alt="{{ $document->typeLabel() }}" class="mt-3 max-h-[32rem] w-full rounded-lg border border-slate-200 bg-slate-50 object-contain">
                        @endif
                    </div>
                @empty
                    <x-empty title="No documents uploaded" />
                @endforelse
            </section>

            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">Owner and registration</h2>
                @foreach ($owners as $owner)
                    @php($profile = $profiles->get($owner->user_id))
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $owner->user?->name }}</dd></div>
                        <div><dt class="text-slate-500">Roles</dt><dd>{{ $owner->roles->pluck('name')->join(', ') }}</dd></div>
                        <div><dt class="text-slate-500">Email</dt><dd>{{ $owner->user?->email }}</dd></div>
                        <div><dt class="text-slate-500">Mobile</dt><dd>{{ $owner->user?->mobile }}</dd></div>
                        @if ($owner->hasRole('doctor'))
                            <div><dt class="text-slate-500">Registration number</dt><dd class="font-medium">{{ $profile?->registration_number ?? 'Not given' }}</dd></div>
                            <div><dt class="text-slate-500">Medical council</dt><dd class="font-medium">{{ $profile?->registration_council ?? 'Not given' }}</dd></div>
                        @endif
                    </dl>
                @endforeach
                <p class="text-sm text-slate-600">Check the number on the <a class="text-brand-700 underline" href="https://www.nmc.org.in/information-desk/indian-medical-register/" target="_blank" rel="noopener noreferrer">Indian Medical Register</a>. That site is outside CurePlus.</p>
            </section>

            <section class="card card-body">
                <h2 class="mb-2 font-semibold text-slate-900">{{ $organization->isSolo() ? 'Chambers' : 'Branches' }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($locations as $location)
                        <li><span class="font-medium">{{ $location->name }}</span> <span class="text-slate-500">{{ collect([$location->address_line, $location->city])->filter()->join(', ') ?: 'No address yet' }}</span></li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-20">
            <section class="card card-body space-y-2">
                <h2 class="font-semibold text-slate-900">Checklist</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($requirements as $requirement)
                        <li class="flex items-start gap-2"><x-icon name="check" @class(['h-5 w-5 flex-none', 'text-emerald-600' => $requirement['done'], 'text-slate-300' => ! $requirement['done']]) /><span>{{ $requirement['label'] }}<span class="sr-only">: {{ $requirement['done'] ? 'done' : 'missing' }}</span></span></li>
                    @endforeach
                </ul>
            </section>
            @if ($organization->status === \App\Enums\OrganizationStatus::Rejected)
                <p class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900"><strong>Rejected:</strong> {{ $organization->rejection_reason }}</p>
            @endif
            @if ($organization->status === \App\Enums\OrganizationStatus::PendingVerification)
                <form method="POST" action="{{ route('admin.organizations.approve', $organization) }}" class="card card-body space-y-2" data-confirm="Approve {{ $organization->name }}? Patients can book with them straight away.">
                    @csrf
                    <button class="btn btn-primary w-full" @disabled(! $isComplete)>Approve</button>
                    @unless ($isComplete)<p class="hint">Available once everything on the checklist is in.</p>@endunless
                </form>
                <form method="POST" action="{{ route('admin.approvals.reject', $organization) }}" class="card card-body space-y-2">
                    @csrf
                    <div>
                        <label class="label" for="reason">Reason for rejecting</label>
                        <textarea class="input" id="reason" name="reason" rows="3" required>{{ old('reason') }}</textarea>
                        <p class="hint">The owner sees this, so say what to fix.</p>
                        @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn-danger w-full">Reject</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.admin>
