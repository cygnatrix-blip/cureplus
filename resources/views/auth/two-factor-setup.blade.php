@php($layout = $portal === 'admin' ? 'layouts.admin' : 'layouts.app')
<x-dynamic-component :component="$layout" title="Two-factor sign-in">
    <div class="mx-auto max-w-2xl space-y-4">
        @if ($enabled)
            <div class="card card-body">
                <p class="font-medium text-slate-900">Two-factor sign-in is on.</p>
                @if ($recoveryCodes)
                    <p class="mt-3 text-sm text-slate-600">Save these recovery codes somewhere safe. Each works once, and they will not be shown again.</p>
                    <ul class="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 font-mono text-sm">
                        @foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach
                    </ul>
                @endif
                <a href="{{ route($portal.'.dashboard') }}" class="btn btn-primary mt-4">Continue</a>
            </div>
        @else
            <div class="card card-body">
                <h2 class="font-semibold text-slate-900">Use an authenticator app (recommended)</h2>
                <p class="mt-1 text-sm text-slate-600">Scan this with Google Authenticator, Microsoft Authenticator or similar, then enter the 6-digit code.</p>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-2">{!! $qr !!}</div>
                    <p class="text-xs break-all text-slate-500">Or enter this key by hand:<br><span class="font-mono text-slate-800">{{ $secret }}</span></p>
                </div>
                <form method="POST" action="{{ route($portal.'.two-factor.totp') }}" class="mt-4 space-y-3">
                    @csrf
                    <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" />
                    <button class="btn btn-primary">Turn on</button>
                </form>
            </div>
            <div class="card card-body">
                <h2 class="font-semibold text-slate-900">Or get a code by email</h2>
                <p class="mt-1 text-sm text-slate-600">We send a code each time you sign in.</p>
                <form method="POST" action="{{ route($portal.'.two-factor.otp.send') }}" class="mt-3">@csrf<button class="btn btn-secondary">Send me a code</button></form>
                <form method="POST" action="{{ route($portal.'.two-factor.otp') }}" class="mt-3 space-y-3">
                    @csrf
                    <x-field name="otp_code" label="Code you received" inputmode="numeric" autocomplete="one-time-code" />
                    <button class="btn btn-secondary">Turn on with codes</button>
                </form>
            </div>
        @endif
    </div>
</x-dynamic-component>
