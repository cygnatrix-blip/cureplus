<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Mask
{
    /**
     * Keeps enough of an email or mobile for support staff to recognise it.
     */
    public static function identifier(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            [$name, $domain] = explode('@', $value, 2);

            return Str::substr($name, 0, 2).'***@'.$domain;
        }

        return Str::mask($value, '*', 3, max(0, Str::length($value) - 5));
    }
}
