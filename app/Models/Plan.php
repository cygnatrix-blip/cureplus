<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, int|null> $limits
 * @property list<string> $features
 */
class Plan extends Model
{
    protected $fillable = ['code', 'name', 'price_paise', 'billing_interval', 'trial_days', 'limits', 'features', 'razorpay_plan_id', 'is_active'];

    protected function casts(): array
    {
        return ['limits' => 'array', 'features' => 'array', 'is_active' => 'boolean', 'price_paise' => 'integer', 'trial_days' => 'integer'];
    }

    public function monthlyPaise(): int
    {
        return $this->billing_interval === 'year' ? (int) round($this->price_paise / 12) : $this->price_paise;
    }
}
