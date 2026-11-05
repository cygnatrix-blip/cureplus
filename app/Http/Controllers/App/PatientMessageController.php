<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\Messaging\Notifier;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PatientMessageController extends Controller
{
    public function whatsapp(Request $request, TenantContext $tenant, Notifier $notifier, AuditLogger $audit): RedirectResponse
    {
        try {
            $message = Crypt::decrypt((string) $request->input('token'));
        } catch (DecryptException) {
            abort(403);
        }

        // The token was made for one practice, and another practice's staff cannot use it.
        abort_unless(is_array($message) && (int) $message['organization_id'] === $tenant->id() && array_key_exists($message['event'], config('messages.events')), 403);

        if ($message['expires'] < now()->timestamp) {
            return back()->with('error', 'This page has been open a long time. Please refresh it and try again.');
        }

        $patient = Patient::query()->findOrFail($message['patient_id']);

        if (! $notifier->sendOnWhatsApp($patient, $message['event'], array_map('strval', array_filter($message['variables'], fn ($value) => $value !== null)))) {
            return back()->with('error', 'Your WhatsApp Business number is not connected. Check it under Integrations.');
        }

        $audit->record('messages.sent_on_whatsapp', $patient, ['event' => $message['event']]);

        return back()->with('success', 'Sending through your WhatsApp Business number.');
    }
}
