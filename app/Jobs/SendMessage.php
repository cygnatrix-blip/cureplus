<?php

namespace App\Jobs;

use App\Contracts\PushSender;
use App\Contracts\SmsProvider;
use App\Mail\ClinicMessageMail;
use App\Models\MessageLog;
use App\Models\PushSubscription;
use App\Services\Messaging\MessageWording;
use App\Services\Messaging\WhatsAppFactory;
use App\Services\OrganizationMail;
use App\Support\MessageText;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Encrypted: the payload holds a name and a phone number while it waits in the jobs table.
 */
class SendMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  array<string, string|int>  $variables
     */
    public function __construct(
        public readonly int $logId,
        public readonly string $channel,
        public readonly string $event,
        public readonly string $to,
        public readonly array $variables,
        public readonly ?int $organizationId,
    ) {
        $this->afterCommit();
    }

    public function handle(SmsProvider $sms, WhatsAppFactory $whatsApp, PushSender $push, OrganizationMail $mail): void
    {
        $definition = config("messages.events.{$this->event}") ?? config("messages.staff_events.{$this->event}") ?? config("messages.account_events.{$this->event}");
        $definition['text'] = app(MessageWording::class)->textFor($this->event, $definition['text']);
        $providerId = null;
        $status = 'sent';

        match ($this->channel) {
            'sms' => $sms->send($this->to, MessageText::render($definition['text'], $this->variables)),
            'whatsapp' => $providerId = $this->sendWhatsApp($whatsApp, $definition),
            'email' => $status = $this->sendEmail($mail, $definition),
            'push' => $status = $this->sendPush($push, $definition),
            default => throw new RuntimeException('Unknown channel.'),
        };

        MessageLog::withoutTenancy()->whereKey($this->logId)->update(['status' => $status, 'provider_id' => $providerId, 'sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        // Only the exception's type: its message can echo the number or the provider's token.
        MessageLog::withoutTenancy()->whereKey($this->logId)->update([
            'status' => 'failed',
            'error' => $exception ? class_basename($exception) : 'unknown',
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function sendWhatsApp(WhatsAppFactory $whatsApp, array $definition): string
    {
        $provider = $whatsApp->forOrganization($this->organizationId) ?? throw new RuntimeException('WhatsApp is not configured.');
        $parameters = array_map(fn (string $key) => (string) ($this->variables[$key] ?? ''), $definition['params']);

        return $provider->sendTemplate($this->to, $definition['whatsapp_template'], $parameters);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function sendEmail(OrganizationMail $mail, array $definition): string
    {
        // No organization means the platform writing to a practice, with the platform's address.
        $mailer = $this->organizationId ? $mail->mailerFor($this->organizationId) : app('mailer');

        // The practice may have disconnected its email while this waited in the queue.
        if ($mailer === null) {
            return 'no_clinic_email';
        }

        $mailer->to($this->to)->send(new ClinicMessageMail(
            MessageText::render($definition['subject'], $this->variables),
            MessageText::render($definition['text'], $this->variables),
        ));

        return 'sent';
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function sendPush(PushSender $push, array $definition): string
    {
        $subscription = PushSubscription::query()->find((int) $this->to);

        if ($subscription === null) {
            return 'skipped';
        }

        $delivered = $push->send($subscription, [
            'title' => $definition['push_title'],
            'body' => MessageText::render($definition['text'], $this->variables),
            'url' => (string) ($this->variables['url'] ?? '/'),
        ]);

        // A browser that has gone for good will never come back, so stop trying it.
        if (! $delivered) {
            $subscription->delete();

            return 'expired';
        }

        return 'sent';
    }
}
