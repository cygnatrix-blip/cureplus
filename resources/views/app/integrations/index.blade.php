<x-layouts.app title="Integrations">
    <div class="mx-auto max-w-3xl space-y-4">
        <p class="text-sm text-slate-600">Choose Razorpay or Cashfree for online payments, or neither. Turning one on turns the other off, and payment links already sent still complete.</p>
        <form method="POST" action="{{ route('app.integrations.razorpay') }}" class="card card-body space-y-3">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-slate-900">Razorpay</h2>
                @if ($razorpay?->last_test_result)<span @class(['badge', 'badge-green' => $razorpay->last_test_result === 'passed', 'badge-red' => $razorpay->last_test_result !== 'passed'])>Last test {{ $razorpay->last_test_result }}</span>@endif
            </div>
            <p class="text-sm text-slate-600">Patient payments go straight to your own Razorpay account. Saved keys are encrypted and cannot be read back; leave a field blank to keep what is saved.</p>
            <x-field name="key_id" label="Key ID" autocomplete="off" :placeholder="$razorpay?->masked('key_id')" />
            <x-field name="key_secret" label="Key Secret" type="password" autocomplete="new-password" :placeholder="$razorpay?->masked('key_secret')" />
            <x-field name="webhook_secret" label="Webhook Secret" type="password" autocomplete="new-password" :placeholder="$razorpay?->masked('webhook_secret')" />
            <div>
                <label class="label" for="mode">Mode</label>
                <select class="input" id="mode" name="mode">
                    @foreach (['test' => 'Test', 'live' => 'Live'] as $value => $label)<option value="{{ $value }}" @selected(($razorpay?->config['mode'] ?? 'test') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($razorpay?->is_enabled)> Accept online payments</label>
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <p class="font-medium">Your webhook URL</p>
                <p class="mt-1 font-mono text-xs break-all">{{ $webhookUrl }}</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-slate-600">
                    <li>In the Razorpay Dashboard, open Account &amp; Settings, then API Keys, and generate a key.</li>
                    <li>Open Webhooks, add the URL above, and choose a secret. Paste the same secret here.</li>
                    <li>Tick the <span class="font-mono">payment_link.paid</span>, <span class="font-mono">refund.processed</span> and <span class="font-mono">refund.failed</span> events.</li>
                    <li>Save, then press Test connection.</li>
                </ol>
            </div>
            <div class="flex flex-wrap gap-2"><button class="btn btn-primary">Save Razorpay</button><a class="btn btn-ghost" href="{{ route('app.support.index', ['subject' => 'Help me set up Razorpay', 'category' => 'razorpay']) }}">Ask us to help</a></div>
        </form>
        <form method="POST" action="{{ route('app.integrations.razorpay.test') }}">@csrf<button class="btn btn-secondary">Test Razorpay connection</button></form>

        <form method="POST" action="{{ route('app.integrations.cashfree') }}" class="card card-body space-y-3">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-slate-900">Cashfree</h2>
                @if ($cashfree?->last_test_result)<span @class(['badge', 'badge-green' => $cashfree->last_test_result === 'passed', 'badge-red' => $cashfree->last_test_result !== 'passed'])>{{ $cashfree->last_test_result === 'passed' ? 'Connected' : 'Not connected' }}</span>@endif
            </div>
            <p class="text-sm text-slate-600">Patient payments go straight to your own Cashfree account. Saved keys are encrypted and cannot be read back; leave a field blank to keep what is saved.</p>
            <x-field name="key_id" label="App ID" autocomplete="off" field-id="cashfree_key_id" :placeholder="$cashfree?->masked('key_id')" />
            <x-field name="key_secret" label="Secret Key" type="password" autocomplete="new-password" field-id="cashfree_key_secret" :placeholder="$cashfree?->masked('key_secret')" />
            <div>
                <label class="label" for="cashfree_mode">Mode</label>
                <select class="input" id="cashfree_mode" name="mode">
                    @foreach (['test' => 'Test (sandbox)', 'live' => 'Live'] as $value => $label)<option value="{{ $value }}" @selected(($cashfree?->config['mode'] ?? 'test') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($cashfree?->is_enabled)> Accept online payments with Cashfree</label>
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <p class="font-medium">Your webhook URL</p>
                <p class="mt-1 font-mono text-xs break-all">{{ $cashfreeWebhookUrl }}</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-slate-600">
                    <li>In the Cashfree Merchant Dashboard, open Payment Gateway, then Developers, and generate your API keys.</li>
                    <li>Under Webhooks, add the URL above for payment link and refund events.</li>
                    <li>Save here, then press Test connection, and make one small test payment before going live.</li>
                </ol>
            </div>
            <button class="btn btn-primary">Save Cashfree</button>
        </form>
        <form method="POST" action="{{ route('app.integrations.cashfree.test') }}">@csrf<button class="btn btn-secondary">Test Cashfree connection</button></form>

        <form method="POST" action="{{ route('app.integrations.smtp') }}" class="card card-body space-y-3">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-slate-900">Your email for patients</h2>
                <span @class(['badge', 'badge-green' => $smtp?->is_enabled, 'badge-amber' => ! $smtp?->is_enabled])>{{ $smtp?->is_enabled ? 'Connected' : 'Not connected' }}</span>
            </div>
            <p class="text-sm text-slate-600">Reminders, prescriptions, bills and other patient emails are sent from your own address, so patients recognise you and replies come to you. <strong>Until this is connected, patients get no email from your practice.</strong> Sign-in and verification codes are sent by CurePlus either way.</p>
            @if (! $smtp?->is_enabled && $emailsNotSent > 0)
                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $emailsNotSent }} {{ \Illuminate\Support\Str::plural('email', $emailsNotSent) }} to your patients could not be sent in the last 30 days because this is not connected.</p>
            @endif
            <p class="text-sm text-slate-600">For Gmail: turn on 2-Step Verification, then create an App Password in your Google Account, and paste it below. Do not use your normal password.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="host" label="SMTP host" :value="$smtp?->config['host'] ?? 'smtp.gmail.com'" />
                <x-field name="port" label="Port" type="number" :value="$smtp?->config['port'] ?? 587" />
                <x-field name="username" label="Username" autocomplete="off" :value="$smtp?->config['username'] ?? null" />
                <x-field name="password" label="App Password" type="password" autocomplete="new-password" :placeholder="$smtp?->masked('password')" />
                <x-field name="from_address" label="From address" type="email" :value="$smtp?->config['from_address'] ?? null" />
                <x-field name="from_name" label="From name" :value="$smtp?->config['from_name'] ?? null" />
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($smtp?->is_enabled)> Use this for patient emails</label>
            <button class="btn btn-primary">Save email</button>
        </form>
        <form method="POST" action="{{ route('app.integrations.smtp.test') }}" class="card card-body flex flex-wrap items-end gap-2">
            @csrf
            <x-field name="to" label="Send a test email to" type="email" required />
            <button class="btn btn-secondary">Send test email</button>
        </form>

        <form method="POST" action="{{ route('app.integrations.whatsapp') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="font-semibold text-slate-900">WhatsApp sender</h2>
            <p class="text-sm text-slate-600">From the WhatsApp Business Platform. Without this, the platform's sender is used if it has one.</p>
            <x-field name="phone_number_id" label="Phone number ID" :value="$whatsapp?->config['phone_number_id'] ?? null" />
            <x-field name="business_account_id" label="Business account ID" :value="$whatsapp?->config['business_account_id'] ?? null" />
            <x-field name="access_token" label="Access token" type="password" autocomplete="new-password" :placeholder="$whatsapp?->masked('access_token')" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($whatsapp?->is_enabled)> Send from this number</label>
            <button class="btn btn-primary">Save WhatsApp</button>
        </form>
    </div>
</x-layouts.app>
