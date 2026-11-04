<?php

namespace App\Rules;

use App\Support\Mobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IndianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Mobile::normalize(is_string($value) ? $value : null) === null) {
            $fail('Enter a valid 10-digit Indian mobile number.');
        }
    }
}
