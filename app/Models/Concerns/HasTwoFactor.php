<?php

namespace App\Models\Concerns;

trait HasTwoFactor
{
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function usesTotp(): bool
    {
        return $this->two_factor_method === 'totp';
    }
}
