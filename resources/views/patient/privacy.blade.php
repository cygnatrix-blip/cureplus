<x-layouts.patient :title="__('Privacy')">
    <section class="card card-body">
        <h1 class="font-semibold text-slate-900">{{ __('Your data') }}</h1>
        <a class="btn btn-secondary mt-3 w-full" href="{{ route('patient.privacy.download') }}">{{ __('Download my data') }}</a>
        <a class="btn btn-secondary mt-2 w-full" href="{{ route('patient.notifications') }}">{{ __('Notifications') }}</a>
    </section>
    <form method="POST" action="{{ route('patient.privacy.requests') }}" class="card card-body mt-4 space-y-3">
        @csrf
        <h2 class="font-semibold text-slate-900">{{ __('Ask us to correct or delete your data') }}</h2>
        <select class="input" name="type" aria-label="{{ __('Select') }}">
            <option value="correction">{{ __('Correct my data') }}</option>
            <option value="deletion">{{ __('Delete my account') }}</option>
        </select>
        <div>
            <label class="label" for="details">{{ __('What should change?') }}</label>
            <textarea class="input" id="details" name="details" rows="3"></textarea>
            @error('details')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <p class="text-xs text-slate-500">{{ __('Clinics may have to keep medical and billing records for the time the law requires, even after your account is deleted.') }}</p>
        <button class="btn btn-primary w-full">{{ __('Send request') }}</button>
    </form>
    @if ($requests->isNotEmpty())
        <section class="card mt-4">
            <div class="card-header"><h2 class="card-title">{{ __('Your requests') }}</h2></div>
            @foreach ($requests as $item)
                <p class="border-b border-slate-100 px-4 py-2 text-sm last:border-b-0">{{ $item->created_at?->format('d M Y') }} · {{ ucfirst($item->type) }} · {{ ucfirst($item->status) }}</p>
            @endforeach
        </section>
    @endif
</x-layouts.patient>
