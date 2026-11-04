<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Not queued, unlike other email: the queue runs once a minute from cron,
 * and a sign-in code that arrives a minute late looks broken.
 */
class OtpCodeMail extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $code) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' code: '.$this->code)
            ->line("Your code is: {$this->code}")
            ->line('It expires in '.config('cureplus.otp.ttl_minutes').' minutes. Never share it with anyone.')
            ->line('If you did not ask for this code, you can ignore this email.');
    }
}
