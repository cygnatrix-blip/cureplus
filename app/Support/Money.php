<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * All money is integer paise. Floats only ever appear in this class's output for display.
 */
final class Money
{
    public static function toPaise(string|int|float|null $rupees): int
    {
        $value = trim((string) $rupees);

        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $match) !== 1) {
            throw new InvalidArgumentException('Not a valid amount.');
        }

        return ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '0', 2, '0');
    }

    public static function format(int $paise): string
    {
        return '₹'.self::plain($paise);
    }

    /**
     * Indian digit grouping: 1,00,000.00.
     */
    public static function plain(int $paise): string
    {
        $rupees = (string) intdiv(abs($paise), 100);
        $fraction = str_pad((string) (abs($paise) % 100), 2, '0', STR_PAD_LEFT);

        if (strlen($rupees) > 3) {
            $tail = substr($rupees, -3);
            $head = substr($rupees, 0, -3);
            $rupees = ltrim(strrev(implode(',', str_split(strrev($head), 2))), ',').','.$tail;
        }

        return ($paise < 0 ? '-' : '').$rupees.'.'.$fraction;
    }

    public static function decimal(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
