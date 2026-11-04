<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Minishlink\WebPush\VAPID;
use Throwable;

class PlatformIntegrationController extends Controller
{
    public function __construct(private readonly PlatformSettings $settings, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.integrations.index', ['settings' => $this->settings]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.mail_host' => ['nullable', 'string', 'max:150'],
            'settings.mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'settings.mail_username' => ['nullable', 'string', 'max:190'],
            'settings.mail_password' => ['nullable', 'string', 'max:200'],
            'settings.mail_from_address' => ['nullable', 'email', 'max:190'],
            'settings.mail_from_name' => ['nullable', 'string', 'max:120'],
            'settings.sms_driver' => ['nullable', 'in:http,log'],
            'settings.sms_url' => ['nullable', 'url:https', 'max:255'],
            'settings.sms_token' => ['nullable', 'string', 'max:500'],
            'settings.sms_sender' => ['nullable', 'string', 'max:20'],
            'settings.whatsapp_token' => ['nullable', 'string', 'max:500'],
            'settings.whatsapp_phone_number_id' => ['nullable', 'string', 'max:60'],
            'settings.push_subject' => ['nullable', 'string', 'max:190'],
            'settings.push_public_key' => ['nullable', 'string', 'max:255'],
            'settings.push_private_key' => ['nullable', 'string', 'max:255'],
            'settings.razorpay_key_id' => ['nullable', 'string', 'max:100'],
            'settings.razorpay_key_secret' => ['nullable', 'string', 'max:200'],
            'settings.razorpay_webhook_secret' => ['nullable', 'string', 'max:200'],
        ]);

        // Form names use underscores for the group separator, so map them back to keys.
        $values = [];

        foreach ($data['settings'] as $name => $value) {
            $values[preg_replace('/_/', '.', $name, 1)] = $value === null ? null : (string) $value;
        }

        $this->settings->save($values);
        $this->audit->record('platform_settings.saved', null, ['fields' => array_keys(array_filter($values, fn ($value) => ! blank($value)))]);

        return back()->with('success', 'Settings saved.');
    }

    public function generateVapid(): RedirectResponse
    {
        $keys = VAPID::createVapidKeys();

        $this->settings->save(['push.public_key' => $keys['publicKey'], 'push.private_key' => $keys['privateKey']]);
        $this->audit->record('platform_settings.vapid_generated');

        return back()->with('success', 'New web-push keys generated. Devices that subscribed with the old keys must subscribe again.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']]);

        try {
            Mail::raw('This is a test email from '.config('app.name').'.', fn ($message) => $message->to($data['to'])->subject('Test email'));
        } catch (Throwable) {
            // The transport's message can echo credentials, so it stays off the page.
            return back()->with('error', 'That did not work. Check the host, port, username and App Password.');
        }

        return back()->with('success', 'Test email sent.');
    }
}
