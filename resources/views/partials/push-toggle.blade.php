@if (\App\Http\Controllers\PushController::isConfigured())
    <div x-data="pushToggle" data-public-key="{{ config('messages.push.public_key') }}" data-subscribe-url="{{ $subscribeUrl }}" data-unsubscribe-url="{{ $unsubscribeUrl }}" x-show="supported" x-cloak>
        <button type="button" class="btn btn-secondary w-full" @click="toggle" x-text="label"></button>
        <p class="hint" x-show="denied" x-cloak>{{ __('Notifications are blocked in your browser settings.') }}</p>
    </div>
@endif
