@props(['mobile', 'event', 'variables', 'link' => null])
@php
    $manual = app(\App\Services\Messaging\ManualMessage::class);
    $messageText = $manual->text($event, $variables);
@endphp
@if ($mobile)
    <span class="inline-flex flex-wrap gap-1 print:hidden">
        <a class="btn btn-sm border-transparent bg-emerald-600 text-white hover:bg-emerald-700" href="{{ $manual->whatsappUrl($mobile, $messageText) }}" target="_blank" rel="noopener noreferrer" title="Opens your WhatsApp with the message ready. Press send there."><x-icon name="chat" class="h-4 w-4" />WhatsApp</a>
        {{-- Only phones have a text message app. --}}
        <a class="btn btn-sm btn-secondary lg:hidden" href="{{ $manual->smsUrl($mobile, $messageText) }}"><x-icon name="chat" class="h-4 w-4" />SMS</a>
        @if ($link)
            <button type="button" class="btn btn-sm btn-secondary" x-data="copyLink" data-link="{{ $link }}" @click="copy"><span x-text="label">Copy link</span></button>
        @endif
    </span>
@endif
