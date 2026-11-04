<?php

namespace App\Support;

final class MessageText
{
    /**
     * @param  array<string, string|int>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        return strtr($template, array_combine(
            array_map(fn (string $key) => '{'.$key.'}', array_keys($variables)),
            array_map(fn (string|int $value) => (string) $value, array_values($variables)),
        ));
    }
}
