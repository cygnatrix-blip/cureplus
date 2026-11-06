<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\OrganizationIntegration;
use App\Services\AuditLogger;
use App\Services\Messaging\WhatsAppFactory;
use App\Services\OrganizationMail;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\UpiPayments;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class IntegrationController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $integrations = OrganizationIntegration::query()->get()->keyBy('kind');

        return view('app.integrations.index', [
            'razorpay' => $integrations->get('razorpay'),
            'cashfree' => $integrations->get('cashfree'),
            'upi' => $integrations->get(UpiPayments::KIND),
            'hasGateway' => $integrations->only(GatewayFactory::KINDS)->contains('is_enabled', true),
            'cashfreeWebhookUrl' => route('webhooks.cashfree', $this->tenant->organization()->uuid),
            'smtp' => $integrations->get('smtp'),
            'emailsNotSent' => MessageLog::withoutTenancy()->where('organization_id', $this->tenant->id())->where('status', 'no_clinic_email')->where('created_at', '>=', now()->subDays(30))->count(),
            'whatsapp' => $integrations->get('whatsapp'),
            'webhookUrl' => route('webhooks.razorpay', $this->tenant->organization()->uuid),
        ]);
    }

    public function saveRazorpay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key_id' => ['nullable', 'string', 'max:100'],
            'key_secret' => ['nullable', 'string', 'max:200'],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $this->save('razorpay', $data, ['key_id', 'key_secret', 'webhook_secret'], ['mode'], (bool) ($data['is_enabled'] ?? false));

        return back()->with('success', 'Razorpay settings saved.');
    }

    public function testRazorpay(GatewayFactory $gateways): RedirectResponse
    {
        $integration = OrganizationIntegration::query()->where('kind', 'razorpay')->first();

        if ($integration === null) {
            return back()->with('error', 'Save your keys first.');
        }

        try {
            $passed = $gateways->make($integration)->testConnection();
        } catch (ConnectionException) {
            $passed = false;
        }

        $integration->forceFill(['last_tested_at' => now(), 'last_test_result' => $passed ? 'passed' : 'failed'])->save();

        return back()->with($passed ? 'success' : 'error', $passed ? 'Razorpay accepted your keys.' : 'Razorpay rejected these keys. Check the Key ID, Key Secret and test/live mode.');
    }

    public function saveCashfree(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key_id' => ['nullable', 'string', 'max:100'],
            'key_secret' => ['nullable', 'string', 'max:200'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $this->save('cashfree', $data, ['key_id', 'key_secret'], ['mode'], (bool) ($data['is_enabled'] ?? false));

        return back()->with('success', 'Cashfree settings saved.');
    }

    public function testCashfree(GatewayFactory $gateways): RedirectResponse
    {
        $integration = OrganizationIntegration::query()->where('kind', 'cashfree')->first();

        if ($integration === null) {
            return back()->with('error', 'Save your keys first.');
        }

        try {
            $passed = $gateways->make($integration)->testConnection();
        } catch (ConnectionException) {
            $passed = false;
        }

        $integration->forceFill(['last_tested_at' => now(), 'last_test_result' => $passed ? 'passed' : 'failed'])->save();

        return back()->with($passed ? 'success' : 'error', $passed ? 'Cashfree accepted your keys.' : 'Cashfree rejected these keys. Check the App ID, Secret Key and test or live mode.');
    }

    /**
     * Any UPI ID works, personal or business: the practice chooses.
     */
    public function saveUpi(Request $request): RedirectResponse
    {
        $request->merge(['vpa' => mb_strtolower(trim((string) $request->input('vpa')))]);

        $data = $request->validate([
            'vpa' => ['nullable', 'required_if_accepted:is_enabled', 'max:100', 'regex:/^[a-z0-9._-]{2,64}@[a-z][a-z0-9]{1,63}$/'],
            'payee_name' => ['nullable', 'required_if_accepted:is_enabled', 'string', 'max:60'],
            'online' => ['required', Rule::in(array_keys(UpiPayments::RULES))],
            'in_person' => ['required', Rule::in(array_keys(UpiPayments::RULES))],
            'is_enabled' => ['sometimes', 'boolean'],
        ], [
            'vpa.regex' => 'This does not look like a UPI ID. It looks like name@bank, for example drroy@okicici.',
            'vpa.required_if_accepted' => 'Enter the UPI ID patients should pay.',
            'payee_name.required_if_accepted' => 'Enter the name patients should see when they pay.',
        ]);

        $this->save(UpiPayments::KIND, $data, [], ['vpa', 'payee_name', 'online', 'in_person'], (bool) ($data['is_enabled'] ?? false));

        return back()->with('success', 'UPI settings saved.');
    }

    public function saveSmtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:150'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:200'],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $this->save('smtp', $data, ['password'], ['host', 'port', 'username', 'from_address', 'from_name'], (bool) ($data['is_enabled'] ?? false));

        return back()->with('success', 'Email settings saved.');
    }

    public function testSmtp(Request $request, OrganizationMail $mail): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        $integration = OrganizationIntegration::query()->where('kind', 'smtp')->first();

        try {
            if ($integration === null) {
                return back()->with('error', 'Save your email details first.');
            }

            $mailer = $mail->build($integration->config);
            $mailer->raw('This is a test email from '.config('app.name').'.', fn ($message) => $message->to($data['to'])->subject('Test email'));
        } catch (Throwable) {
            // The transport's message can echo credentials, so it stays out of the page.
            return back()->with('error', 'That did not work. Check the host, port, username and App Password.');
        }

        return back()->with('success', 'Test email sent.');
    }

    public function saveWhatsapp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone_number_id' => ['nullable', 'string', 'max:60'],
            'business_account_id' => ['nullable', 'string', 'max:60'],
            'access_token' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $this->save('whatsapp', $data, ['access_token'], ['phone_number_id', 'business_account_id'], (bool) ($data['is_enabled'] ?? false));

        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsapp(WhatsAppFactory $whatsApp): RedirectResponse
    {
        $integration = OrganizationIntegration::query()->where('kind', 'whatsapp')->first();
        $provider = $integration ? $whatsApp->fromIntegration($integration) : null;

        if ($integration === null || $provider === null) {
            return back()->with('error', 'Save your phone number ID and access token first.');
        }

        try {
            $passed = $provider->testConnection();
        } catch (ConnectionException) {
            $passed = false;
        }

        $integration->forceFill(['last_tested_at' => now(), 'last_test_result' => $passed ? 'passed' : 'failed'])->save();

        return back()->with($passed ? 'success' : 'error', $passed ? 'WhatsApp accepted your number and token.' : 'WhatsApp rejected these details. Check the phone number ID and access token.');
    }

    /**
     * A blank secret field means "keep what is saved", because saved secrets are never
     * sent back to the form. Only field names go to the audit log, never values.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $secrets
     * @param  list<string>  $plain
     */
    private function save(string $kind, array $data, array $secrets, array $plain, bool $enabled): void
    {
        $integration = OrganizationIntegration::query()->firstOrNew(['kind' => $kind]);
        $config = $integration->exists ? $integration->config : [];

        foreach ($plain as $key) {
            $config[$key] = $data[$key] ?? null;
        }

        foreach ($secrets as $key) {
            if (! blank($data[$key] ?? null)) {
                $config[$key] = $data[$key];
            }
        }

        $integration->fill(['config' => $config, 'is_enabled' => $enabled])->save();

        // One gateway takes new payments at a time, so a patient is never sent to the wrong one.
        // Links already sent through the other still complete, because its keys are kept.
        if ($enabled && in_array($kind, GatewayFactory::KINDS, true)) {
            OrganizationIntegration::query()->whereIn('kind', GatewayFactory::KINDS)->where('kind', '!=', $kind)->update(['is_enabled' => false]);
        }

        $this->audit->record('integrations.saved', $this->tenant->organization(), ['integration' => $kind, 'enabled' => $enabled]);
    }
}
