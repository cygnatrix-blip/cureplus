<?php

namespace App\Http\Controllers;

use App\Contracts\PushSender;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shared by the clinic and patient portals. The signed-in account comes from the
 * route's own guard, so nobody can subscribe a device for someone else.
 */
class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $subscriber = $this->subscriber($request);

        // Keyed by endpoint: one device, one row, even if someone else used it before.
        PushSubscription::query()->updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'subscriber_type' => PushSubscription::subscriberType($subscriber),
            'subscriber_id' => $subscriber->getKey(),
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return response()->json(['status' => 'subscribed']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        $subscriber = $this->subscriber($request);

        PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->where('subscriber_type', PushSubscription::subscriberType($subscriber))
            ->where('subscriber_id', $subscriber->getKey())
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['array'], 'enabled.*' => ['string']]);
        $subscriber = $this->subscriber($request);
        $muted = array_values(array_diff(array_keys(config('messages.events')), $data['enabled'] ?? []));

        NotificationPreference::query()->updateOrCreate(
            ['subscriber_type' => PushSubscription::subscriberType($subscriber), 'subscriber_id' => $subscriber->getKey()],
            ['muted_events' => $muted],
        );

        return back()->with('success', __('Saved.'));
    }

    public static function isConfigured(): bool
    {
        return app(PushSender::class)->isConfigured();
    }

    private function subscriber(Request $request): Model
    {
        /** @var Model */
        return $request->user();
    }
}
