<?php

namespace App\Rules;

use App\Contracts\CaptchaVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PassesCaptcha implements ValidationRule
{
    // Implicit, so the rule still runs when a bot leaves the field out entirely.
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CaptchaVerifier::class)->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Please complete the security check.');
        }
    }
}
