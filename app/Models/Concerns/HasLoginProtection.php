<?php

namespace App\Models\Concerns;

trait HasLoginProtection
{
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function recordFailedLogin(): void
    {
        $attempts = $this->failed_login_attempts + 1;
        $locks = $attempts >= config('cureplus.security.max_login_attempts');

        $this->forceFill([
            'failed_login_attempts' => $locks ? 0 : $attempts,
            'locked_until' => $locks ? now()->addMinutes(config('cureplus.security.lockout_minutes')) : $this->locked_until,
        ])->saveQuietly();
    }

    public function recordSuccessfulLogin(?string $ip): void
    {
        $this->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->saveQuietly();
    }
}
