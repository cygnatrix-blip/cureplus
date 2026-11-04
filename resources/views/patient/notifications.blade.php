<x-layouts.patient :title="__('Notifications')">
    <form method="POST" action="{{ route('patient.notifications.save') }}" class="card card-body space-y-3">
        @csrf
        <h1 class="font-semibold text-slate-900">{{ __('Notifications') }}</h1>
        <p class="text-sm text-slate-600">{{ __('Choose what we tell you about.') }}</p>
        @foreach (config('messages.events') as $key => $event)
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="enabled[]" value="{{ $key }}" @checked(! in_array($key, $muted, true))> {{ __($event['label']) }}</label>
        @endforeach
        <button class="btn btn-primary w-full">{{ __('Save') }}</button>
    </form>
    <div class="mt-4">@include('partials.push-toggle', ['subscribeUrl' => route('patient.push.subscribe'), 'unsubscribeUrl' => route('patient.push.unsubscribe')])</div>
</x-layouts.patient>
