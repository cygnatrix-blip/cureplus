<x-layouts.guest title="Register" wide>
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Register your practice</h1>
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" x-data="registerType" data-initial="{{ old('type', 'solo_practice') }}">
        @csrf
        <fieldset>
            <legend class="label">Are you a clinic or a doctor?</legend>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 p-3 has-checked:border-brand-600 has-checked:bg-brand-50">
                    <input type="radio" name="type" value="solo_practice" @change="pickSolo" @checked(old('type', 'solo_practice') === 'solo_practice')>
                    <span class="text-sm font-medium">Doctor</span>
                </label>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 p-3 has-checked:border-brand-600 has-checked:bg-brand-50">
                    <input type="radio" name="type" value="clinic" @change="pickClinic" @checked(old('type') === 'clinic')>
                    <span class="text-sm font-medium">Clinic</span>
                </label>
            </div>
            @error('type')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <div x-show="isClinic" x-cloak>
            <x-field name="organization_name" label="Clinic name" />
        </div>
        <x-field name="name" label="Your full name" autocomplete="name" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="email" label="Email" type="email" autocomplete="email" required />
            <x-field name="mobile" label="Mobile number" type="tel" inputmode="numeric" autocomplete="tel" required />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="password" label="Password" type="password" autocomplete="new-password" hint="At least 10 characters, with letters and numbers." required />
            <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="terms" value="1" class="mt-1" @checked(old('terms'))>
            <span>I agree to the <a class="underline" target="_blank" rel="noopener" href="{{ route('terms') }}">terms</a> and <a class="underline" target="_blank" rel="noopener" href="{{ route('privacy') }}">privacy policy</a>.</span>
        </label>
        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        @include('partials.captcha')
        <button class="btn btn-primary w-full">Create account</button>
    </form>
    <p class="mt-4 text-center text-sm text-slate-600">Already registered? <a class="text-brand-700 underline" href="{{ route('login') }}">Sign in</a></p>
</x-layouts.guest>
