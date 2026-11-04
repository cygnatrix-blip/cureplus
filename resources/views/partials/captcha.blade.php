@if (app(\App\Contracts\CaptchaVerifier::class)->enabled())
    <div class="cf-turnstile" data-sitekey="{{ config('cureplus.captcha.turnstile.site_key') }}"></div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" async defer></script>
    @error('cf-turnstile-response')<p class="field-error">{{ $message }}</p>@enderror
@endif
