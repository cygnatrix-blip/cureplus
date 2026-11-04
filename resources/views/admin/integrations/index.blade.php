@php($secret = fn (string $key) => $settings->masked($key))
<x-layouts.admin title="Integrations">
    <div class="mx-auto max-w-3xl space-y-4">
        <p class="text-sm text-slate-600">Values saved here replace those in .env. Saved secrets are encrypted and never shown again; leave a secret blank to keep it.</p>
        <form method="POST" action="{{ route('admin.integrations.save') }}" class="space-y-4">
            @csrf
            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">Email (SMTP)</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="settings[mail_host]" label="Host" :value="$settings->get('mail.host', config('mail.mailers.smtp.host'))" />
                    <x-field name="settings[mail_port]" label="Port" type="number" :value="$settings->get('mail.port', (string) config('mail.mailers.smtp.port'))" />
                    <x-field name="settings[mail_username]" label="Username" autocomplete="off" :value="$settings->get('mail.username')" />
                    <x-field name="settings[mail_password]" label="App Password" type="password" autocomplete="new-password" :placeholder="$secret('mail.password')" />
                    <x-field name="settings[mail_from_address]" label="From address" type="email" :value="$settings->get('mail.from_address')" />
                    <x-field name="settings[mail_from_name]" label="From name" :value="$settings->get('mail.from_name')" />
                </div>
            </section>
            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">SMS gateway</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label" for="sms_driver">Driver</label><select class="input" id="sms_driver" name="settings[sms_driver]"><option value="">From .env</option><option value="http" @selected($settings->get('sms.driver') === 'http')>HTTP gateway</option><option value="log" @selected($settings->get('sms.driver') === 'log')>Log (development only)</option></select></div>
                    <x-field name="settings[sms_url]" label="Gateway URL" type="url" :value="$settings->get('sms.url')" />
                    <x-field name="settings[sms_token]" label="Token" type="password" autocomplete="new-password" :placeholder="$secret('sms.token')" />
                    <x-field name="settings[sms_sender]" label="Sender ID" :value="$settings->get('sms.sender')" />
                </div>
            </section>
            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">WhatsApp Business</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="settings[whatsapp_phone_number_id]" label="Phone number ID" :value="$settings->get('whatsapp.phone_number_id')" />
                    <x-field name="settings[whatsapp_token]" label="Access token" type="password" autocomplete="new-password" :placeholder="$secret('whatsapp.token')" />
                </div>
            </section>
            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">Web push (VAPID)</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="settings[push_subject]" label="Contact (mailto: or https:)" :value="$settings->get('push.subject')" />
                    <x-field name="settings[push_public_key]" label="Public key" :value="$settings->get('push.public_key')" />
                    <x-field name="settings[push_private_key]" label="Private key" type="password" autocomplete="new-password" :placeholder="$secret('push.private_key')" />
                </div>
            </section>
            @if (config('plans.enabled'))
            <section class="card card-body space-y-3">
                <h2 class="font-semibold text-slate-900">Platform Razorpay (subscriptions)</h2>
                <p class="text-sm text-slate-600">Webhook URL: <span class="font-mono text-xs break-all">{{ route('webhooks.razorpay.platform') }}</span></p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="settings[razorpay_key_id]" label="Key ID" autocomplete="off" :value="$settings->get('razorpay.key_id')" />
                    <x-field name="settings[razorpay_key_secret]" label="Key Secret" type="password" autocomplete="new-password" :placeholder="$secret('razorpay.key_secret')" />
                    <x-field name="settings[razorpay_webhook_secret]" label="Webhook Secret" type="password" autocomplete="new-password" :placeholder="$secret('razorpay.webhook_secret')" />
                </div>
            </section>
            @endif
            <button class="btn btn-primary">Save all</button>
        </form>
        <section class="card card-body flex flex-wrap items-end gap-2">
            <form method="POST" action="{{ route('admin.integrations.test-email') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-field name="to" label="Send a test email to" type="email" required />
                <button class="btn btn-secondary">Send</button>
            </form>
            <form method="POST" action="{{ route('admin.integrations.vapid') }}" data-confirm="Generate new push keys? Every subscribed device must subscribe again.">@csrf<button class="btn btn-secondary">Generate web-push keys</button></form>
        </section>
        <p class="text-sm text-slate-600">Storage: files use the disk set by FILESYSTEM_DISK in .env. An S3-compatible India-region disk can be configured in config/filesystems.php.</p>
    </div>
</x-layouts.admin>
