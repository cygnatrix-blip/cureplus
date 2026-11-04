<x-layouts.booking :organization="$organization" :title="__('Your details')">
    <div class="mx-auto max-w-6xl px-4 py-6">
        @include('partials.flash')
        <div class="mb-5">@include('booking.partials.steps', ['current' => 2])</div>
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-4 lg:col-span-2">
                @if ($account)
                    <section class="card p-5">
                        <h1 class="text-lg font-semibold text-slate-900">{{ __('Who is this appointment for?') }}</h1>
                        <form method="POST" action="{{ route('book.confirm', $organization->slug) }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                            @csrf
                            @foreach ($family as $member)
                                <button class="card flex items-center gap-3 p-3 text-left transition hover:border-brand-600" name="patient_id" value="{{ $member->id }}">
                                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}</span>
                                    <span class="min-w-0 truncate font-medium text-slate-900">{{ $member->name }}</span>
                                </button>
                            @endforeach
                        </form>
                        <form method="POST" action="{{ route('book.confirm', $organization->slug) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-5">
                            @csrf
                            <h2 class="font-semibold text-slate-900">{{ __('Someone new') }}</h2>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field name="name" :label="__('Full name')" />
                                <x-field name="mobile" :label="__('Mobile number')" type="tel" inputmode="numeric" :value="$account->mobile" />
                                <x-field name="age" :label="__('Age')" type="number" min="0" max="130" />
                                <div>
                                    <label class="label" for="gender">{{ __('Gender') }}</label>
                                    <select class="input" id="gender" name="gender"><option value="">{{ __('Select') }}</option><option value="male">{{ __('Male') }}</option><option value="female">{{ __('Female') }}</option><option value="other">{{ __('Other') }}</option></select>
                                </div>
                                <x-field name="relation" :label="__('Relation')" />
                            </div>
                            @include('booking.partials.consent')
                            <button class="btn btn-primary w-full sm:w-auto sm:px-10">{{ __('Confirm booking') }}</button>
                        </form>
                    </section>
                @else
                    <form method="POST" action="{{ route('book.otp', $organization->slug) }}" class="card space-y-4 p-5">
                        @csrf
                        <div>
                            <h1 class="text-lg font-semibold text-slate-900">{{ __('Your details') }}</h1>
                            <p class="text-sm text-slate-600">{{ __('We will email you a code to confirm it is you. No password needed.') }}</p>
                        </div>
                        <x-field name="name" :label="__('Full name')" :value="$pending['name'] ?? null" autocomplete="name" required />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="email" :label="__('Email')" type="email" :value="$pending['email'] ?? null" autocomplete="email" required />
                            <x-field name="mobile" :label="__('Mobile number')" type="tel" inputmode="numeric" :value="$pending['mobile'] ?? null" autocomplete="tel" required />
                            <x-field name="age" :label="__('Age')" type="number" min="0" max="130" :value="$pending['age'] ?? null" />
                            <div>
                                <label class="label" for="gender">{{ __('Gender') }}</label>
                                <select class="input" id="gender" name="gender"><option value="">{{ __('Select') }}</option>@foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('gender', $pending['gender'] ?? null) === $value)>{{ __($label) }}</option>@endforeach</select>
                            </div>
                        </div>
                        @include('booking.partials.consent', ['checked' => (bool) $pending])
                        @include('partials.captcha')
                        <button @class(['btn w-full sm:w-auto sm:px-10', 'btn-primary' => ! $pending, 'btn-secondary' => $pending])>{{ $pending ? __('Send a new code') : __('Email me a code') }}</button>
                    </form>
                    @if ($pending)
                        <form method="POST" action="{{ route('book.confirm', $organization->slug) }}" class="card space-y-3 border-brand-600 p-5 ring-2 ring-brand-600">
                            @csrf
                            <h2 class="font-semibold text-slate-900">{{ __('Enter the code') }}</h2>
                            <p class="text-sm text-slate-600">{{ __('We sent a 6-digit code to :email. Check your spam folder if you do not see it.', ['email' => $pending['email']]) }}</p>
                            <x-field name="code" :label="__('6-digit code')" inputmode="numeric" autocomplete="one-time-code" class="tracking-[0.4em] sm:max-w-xs" required autofocus />
                            <button class="btn btn-primary w-full sm:w-auto sm:px-10">{{ __('Confirm booking') }}</button>
                        </form>
                    @endif
                @endif
            </div>
            <div>@include('booking.partials.summary')</div>
        </div>
    </div>
</x-layouts.booking>
