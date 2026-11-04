<?php

namespace App\Support;

/**
 * Turns a clinic's chosen colours into the theme variables Tailwind's utilities read.
 * Whatever colour is picked, button text stays readable: the shade used behind white
 * text is darkened until it meets the WCAG AA contrast of 4.5 to 1.
 */
final class BrandTheme
{
    public const CORNERS = ['sharp' => '0.25rem', 'soft' => '0.75rem', 'round' => '1.25rem'];

    public const PRESETS = [
        'Clinical teal' => ['#088575', '#f59e0b'],
        'Trust blue' => ['#1d4ed8', '#0ea5e9'],
        'Royal indigo' => ['#4338ca', '#ec4899'],
        'Care green' => ['#15803d', '#eab308'],
        'Warm rose' => ['#be123c', '#f97316'],
        'Graphite' => ['#334155', '#14b8a6'],
    ];

    /**
     * Inputs are validated as #rrggbb on save and checked again here,
     * because this output goes into a style block on public pages.
     */
    public static function css(?string $primary, ?string $accent, ?string $corners = null): string
    {
        $variables = [];

        if (self::isHex($primary)) {
            foreach (self::shades((string) $primary) as $shade => $hex) {
                $variables["--color-brand-{$shade}"] = $hex;
            }
        }

        if (self::isHex($accent)) {
            $variables['--color-accent-500'] = $accent;
            $variables['--color-accent-100'] = self::mix((string) $accent, '#ffffff', 0.85);
        }

        if (isset(self::CORNERS[$corners ?? ''])) {
            $variables['--ui-radius'] = self::CORNERS[$corners];
        }

        if ($variables === []) {
            return '';
        }

        $body = implode('', array_map(fn (string $name, string $value) => "{$name}:{$value};", array_keys($variables), $variables));

        return ":root{{$body}}";
    }

    /**
     * @return array<int, string>
     */
    public static function shades(string $primary): array
    {
        $base = self::readableOnWhiteText($primary);

        return [
            50 => self::mix($primary, '#ffffff', 0.93),
            100 => self::mix($primary, '#ffffff', 0.85),
            200 => self::mix($primary, '#ffffff', 0.7),
            300 => self::mix($primary, '#ffffff', 0.5),
            400 => self::mix($primary, '#ffffff', 0.25),
            500 => $primary,
            600 => $base,
            700 => self::mix($base, '#000000', 0.15),
            800 => self::mix($base, '#000000', 0.3),
            900 => self::mix($base, '#000000', 0.45),
            950 => self::mix($base, '#000000', 0.65),
        ];
    }

    public static function readableOnWhiteText(string $hex): string
    {
        $color = $hex;

        for ($step = 0; $step < 30 && self::contrastWithWhite($color) < 4.5; $step++) {
            $color = self::mix($color, '#000000', 0.06);
        }

        return $color;
    }

    public static function contrastWithWhite(string $hex): float
    {
        return 1.05 / (self::luminance($hex) + 0.05);
    }

    public static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    private static function mix(string $from, string $to, float $amount): string
    {
        $mixed = '#';

        foreach ([1, 3, 5] as $offset) {
            $a = hexdec(substr($from, $offset, 2));
            $b = hexdec(substr($to, $offset, 2));
            $mixed .= str_pad(dechex((int) round($a + ($b - $a) * $amount)), 2, '0', STR_PAD_LEFT);
        }

        return $mixed;
    }

    private static function luminance(string $hex): float
    {
        $total = 0.0;

        foreach ([1 => 0.2126, 3 => 0.7152, 5 => 0.0722] as $offset => $weight) {
            $channel = hexdec(substr($hex, $offset, 2)) / 255;
            $total += $weight * ($channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4);
        }

        return $total;
    }
}
