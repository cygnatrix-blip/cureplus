<x-layouts.guest title="Register" wide>
    <h1 class="text-lg font-semibold text-slate-900">Register your practice</h1>
    <p class="mb-4 text-sm text-slate-600">We check every practice before patients can book, usually within 24 hours. Giving everything here means we will not need to call you.</p>
    <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="registerType" data-initial="{{ old('type', 'solo_practice') }}" data-owner-doctor="{{ old('owner_is_doctor') ? '1' : '0' }}">
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
            <x-field name="organization_name" label="Clinic name" x-bind:required="isClinic" />
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
        <fieldset class="space-y-3 border-t border-slate-100 pt-4">
            <legend class="text-sm font-semibold text-slate-900"><span x-show="isClinic" x-cloak>Clinic address</span><span x-show="isDoctorType">Chamber address</span></legend>
            <x-field name="address_line" label="Address" autocomplete="street-address" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="city" label="City" autocomplete="address-level2" required />
                <x-field name="pincode" label="PIN code (optional)" inputmode="numeric" autocomplete="postal-code" maxlength="6" />
            </div>
        </fieldset>
        <label class="flex items-start gap-2 text-sm text-slate-700" x-show="isClinic" x-cloak>
            <input type="checkbox" name="owner_is_doctor" value="1" class="mt-1" @change="toggleOwnerDoctor" @checked(old('owner_is_doctor'))>
            <span>I also see patients at this clinic</span>
        </label>
        <fieldset class="space-y-3 border-t border-slate-100 pt-4" x-show="needsDoctorDetails">
            <legend class="text-sm font-semibold text-slate-900">Your medical registration</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="registration_number" label="Registration number" x-bind:required="needsDoctorDetails" />
                <x-field name="registration_council" label="State medical council" list="councils" x-bind:required="needsDoctorDetails" />
                <x-field name="qualification" label="Qualification" placeholder="MBBS, MD" x-bind:required="needsDoctorDetails" />
                <x-field name="specialty" label="Specialty (optional)" placeholder="General Medicine" />
            </div>
            <datalist id="councils">@foreach ($councils as $council)<option value="{{ $council }}"></option>@endforeach</datalist>
        </fieldset>
        <div class="border-t border-slate-100 pt-4">
            <label class="label" for="document"><span x-show="isClinic" x-cloak>Clinic proof</span><span x-show="isDoctorType">Registration certificate (optional)</span></label>
            <input class="input" id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" x-bind:required="isClinic">
            <p class="hint"><span x-show="isClinic" x-cloak>Clinic licence, rent agreement, GST certificate or utility bill. </span>PDF, JPG or PNG, up to 5 MB. A clear phone photo is fine.</p>
            @error('document')<p class="field-error">{{ $message }}</p>@enderror
            @if ($errors->any() && ! $errors->has('document'))<p class="field-error">Browsers forget a chosen file when a form has to be corrected, so please choose it again.</p>@endif
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
