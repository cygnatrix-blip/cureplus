<?php

namespace App\Support;

/**
 * The medical systems and the specialties within each.
 */
class Specialties
{
    /**
     * @return array<string, list<string>>
     */
    public static function bySystem(): array
    {
        return config('specialties.systems');
    }

    /**
     * @return list<string>
     */
    public static function systems(): array
    {
        return array_keys(self::bySystem());
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::bySystem()));
    }

    /**
     * What a form may submit. A value saved before this list existed stays valid for that
     * record, so a doctor can still save their profile without being forced to change it.
     *
     * @return list<string>
     */
    public static function allowed(?string $current = null): array
    {
        return array_values(array_filter([...self::all(), config('specialties.other'), $current]));
    }

    public static function isAllied(?string $specialty): bool
    {
        return self::systemOf($specialty) === 'Allied Health';
    }

    public static function systemOf(?string $specialty): ?string
    {
        foreach (self::bySystem() as $system => $specialties) {
            if (in_array($specialty, $specialties, true)) {
                return $system;
            }
        }

        return null;
    }
}
