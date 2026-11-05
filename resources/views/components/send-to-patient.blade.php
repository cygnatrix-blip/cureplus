@props(['patient', 'event', 'variables', 'link' => null, 'labelled' => false])
@php
    $manual = app(\App\Services\Messaging\ManualMessage::class);
    $messageText = $manual->text($event, array_map('strval', array_filter($variables, fn ($value) => $value !== null)));
    $organizationId = (int) app(\App\Tenancy\TenantContext::class)->id();
    $hasBusiness = $manual->hasWhatsAppBusiness($organizationId);
@endphp
@if ($patient?->mobile)
    <span class="inline-flex flex-wrap items-center justify-end gap-1 print:hidden" x-data="smsHint">
        @if ($labelled)<span class="mr-1 text-sm text-slate-600">{{ $hasBusiness ? 'Send to patient:' : 'Send from your clinic\'s phone:' }}</span>@endif
        @if ($hasBusiness)
            <form method="POST" action="{{ route('app.messages.whatsapp') }}" class="inline">
                @csrf
                <input type="hidden" name="token" value="{{ $manual->sendToken($organizationId, (int) $patient->id, $event, $variables) }}">
                <button class="btn btn-sm border-transparent bg-emerald-600 text-white hover:bg-emerald-700" title="Sends straight away from your WhatsApp Business number."><x-icon name="chat" class="h-4 w-4" />WhatsApp Business</button>
            </form>
        @else
            <a class="btn btn-sm border-transparent bg-emerald-600 text-white hover:bg-emerald-700" href="{{ $manual->whatsappUrl($patient->mobile, $messageText) }}" target="_blank" rel="noopener noreferrer" title="Opens WhatsApp on this device with the message ready. Press send there."><x-icon name="chat" class="h-4 w-4" />WhatsApp</a>
        @endif
        {{-- A phone opens its text message app. A computer has none, so it explains what to do. --}}
        <a class="btn btn-sm btn-secondary lg:hidden" href="{{ $manual->smsUrl($patient->mobile, $messageText) }}"><x-icon name="phone" class="h-4 w-4" />SMS</a>
        <button type="button" class="btn btn-sm btn-secondary hidden lg:inline-flex" @click="toggle"><x-icon name="phone" class="h-4 w-4" />SMS</button>
        @if ($link)
            <button type="button" class="btn btn-sm btn-secondary" x-data="copyLink" data-link="{{ $link }}" @click="copy"><span x-text="label">Copy link</span></button>
        @endif
        <span class="hidden w-full text-right text-xs text-slate-600 lg:block" x-show="open" x-cloak role="status">SMS opens on a phone. Open this page on your phone to send it.</span>
    </span>
@endif
