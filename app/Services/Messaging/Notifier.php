<?php

namespace App\Services\Messaging;

use App\Contracts\PushSender;
use App\Jobs\SendMessage;
use App\Models\MessageLog;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PlatformAdmin;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Billing\FeatureGate;
use App\Services\Billing\PlanLimits;
use App\Services\OrganizationMail;
use App\Support\Mask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * The single entry point for automatic messages. It decides who gets what on which
 * channel; SendMessage does the sending, on the queue.
 */
class Notifier
{
    public function __construct(
        private readonly WhatsAppFactory $whatsApp,
        private readonly PushSender $push,
        private readonly PlanLimits $limits,
        private readonly FeatureGate $features,
        private readonly OrganizationMail $mail,
    ) {}

    /**
     * WhatsApp is preferred and SMS is the fallback, not both, since each one costs money.
     * Email and push are free, so they are added whenever they are available.
     *
     * @param  array<string, string|int>  $variables
     */
    public function notifyPatient(Patient $patient, string $event, array $variables, ?string $dedupeKey = null, ?string $url = null): void
    {
        $account = $patient->patient_account_id ? PatientAccount::query()->find($patient->patient_account_id) : null;

        if ($account && $this->isMuted($account, $event)) {
            return;
        }

        $variables += ['name' => $patient->name];
        $organizationId = (int) $patient->organization_id;
        // Frequent events, such as chat replies, would cost the practice a paid message each time.
        $channel = config("messages.events.{$event}.free_only") ? null : $this->paidChannel($organizationId);

        if ($channel === null) {
            // Nothing paid is set up yet, so email and push carry the message.
        } elseif ($this->limits->canSendPaidMessage($organizationId)) {
            $this->queue($organizationId, $channel, $event, $patient->mobile, $variables, $dedupeKey);
        } else {
            // Recorded so the owner can see why a patient was not told.
            MessageLog::withoutTenancy()->create(['organization_id' => $organizationId, 'channel' => $channel, 'event' => $event, 'recipient_masked' => Mask::identifier($patient->mobile), 'status' => 'over_limit']);
        }

        if ($patient->email && $this->mail->hasOwnEmail($organizationId)) {
            $this->queue($organizationId, 'email', $event, $patient->email, $variables, $dedupeKey);
        } elseif ($patient->email) {
            // Recorded so the owner can see why patients are not being emailed.
            MessageLog::withoutTenancy()->create(['organization_id' => $organizationId, 'channel' => 'email', 'event' => $event, 'recipient_masked' => Mask::identifier($patient->email), 'status' => 'no_clinic_email']);
        }

        if ($account) {
            $this->pushTo($account, $organizationId, $event, $variables, $url ?? route('patient.home'), $dedupeKey);
        }
    }

    /**
     * @param  array<string, string|int>  $variables
     */
    public function notifyUser(User $user, ?int $organizationId, string $event, array $variables, string $url, ?string $dedupeKey = null): void
    {
        if (! $this->isMuted($user, $event)) {
            $this->pushTo($user, $organizationId, $event, $variables + ['name' => $user->name], $url, $dedupeKey);
        }
    }

    /**
     * Staff pressing the WhatsApp button at a practice that has WhatsApp Business connected.
     *
     * @param  array<string, string|int>  $variables
     */
    public function sendOnWhatsApp(Patient $patient, string $event, array $variables): bool
    {
        $organizationId = (int) $patient->organization_id;

        if ($this->paidChannel($organizationId) !== 'whatsapp') {
            return false;
        }

        $this->queue($organizationId, 'whatsapp', $event, $patient->mobile, $variables + ['name' => $patient->name], null);

        return true;
    }

    /**
     * Whether a message could actually reach this patient, so staff are never told "sent" when it was not.
     */
    public function canReachPatient(Patient $patient): bool
    {
        return ($patient->email && $this->mail->hasOwnEmail((int) $patient->organization_id))
            || $this->paidChannel((int) $patient->organization_id) !== null;
    }

    /**
     * The platform writing to a practice owner about their account. It uses the platform's
     * own sender, which is why it works before the practice itself is allowed to message.
     *
     * @param  array<string, string|int>  $variables
     */
    public function notifyAccountOwner(User $owner, string $event, array $variables, ?string $dedupeKey = null): void
    {
        $variables += ['name' => $owner->name];

        if ($owner->email) {
            $this->queue(null, 'email', $event, $owner->email, $variables, $dedupeKey);
        }

        if ($owner->mobile && $this->whatsApp->forOrganization(null) !== null) {
            $this->queue(null, 'whatsapp', $event, $owner->mobile, $variables, $dedupeKey);
        }
    }

    /**
     * @param  array<string, string|int>  $variables
     */
    public function notifyPlatformAdmins(string $event, array $variables): void
    {
        foreach (PlatformAdmin::query()->where('role', 'super_admin')->where('status', 'active')->get() as $admin) {
            $this->queue(null, 'email', $event, (string) $admin->email, $variables, null);
        }
    }

    private function paidChannel(int $organizationId): ?string
    {
        // A practice that is not approved, or is suspended, sends nothing paid to anyone.
        if (! Organization::query()->find($organizationId)?->isActive()) {
            return null;
        }

        if ($this->features->enabled($organizationId, 'whatsapp') && $this->whatsApp->forOrganization($organizationId) !== null) {
            return 'whatsapp';
        }

        return match (config('cureplus.sms.driver')) {
            'http' => blank(config('cureplus.sms.http.url')) ? null : 'sms',
            default => app()->isProduction() ? null : 'sms',
        };
    }

    /**
     * @return list<string>
     */
    public function mutedEvents(Model $subscriber): array
    {
        return NotificationPreference::query()
            ->where('subscriber_type', PushSubscription::subscriberType($subscriber))
            ->where('subscriber_id', $subscriber->getKey())
            ->first()
            ->muted_events ?? [];
    }

    private function isMuted(Model $subscriber, string $event): bool
    {
        return in_array($event, $this->mutedEvents($subscriber), true);
    }

    /**
     * @param  array<string, string|int>  $variables
     */
    private function pushTo(Model $subscriber, ?int $organizationId, string $event, array $variables, string $url, ?string $dedupeKey): void
    {
        if (! $this->push->isConfigured()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->where('subscriber_type', PushSubscription::subscriberType($subscriber))
            ->where('subscriber_id', $subscriber->getKey())
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->queue($organizationId, 'push', $event, (string) $subscription->id, $variables + ['url' => $url], $dedupeKey ? "{$dedupeKey}:{$subscription->id}" : null);
        }
    }

    /**
     * @param  array<string, string|int>  $variables
     */
    private function queue(?int $organizationId, string $channel, string $event, string $to, array $variables, ?string $dedupeKey): void
    {
        try {
            $log = MessageLog::withoutTenancy()->create([
                'organization_id' => $organizationId,
                'channel' => $channel,
                'event' => $event,
                'recipient_masked' => $channel === 'push' ? 'device' : Mask::identifier($to),
                'status' => 'queued',
                'dedupe_key' => $dedupeKey ? "{$dedupeKey}:{$channel}" : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        // A message is never worth failing a payment or a booking over. The money was taken
        // and the slot is held, so the failure is recorded and the work carries on.
        try {
            SendMessage::dispatch($log->id, $channel, $event, $to, $variables, $organizationId);
        } catch (Throwable $exception) {
            report($exception);
            $log->forceFill(['status' => 'failed', 'error' => class_basename($exception)])->save();
        }
    }
}
