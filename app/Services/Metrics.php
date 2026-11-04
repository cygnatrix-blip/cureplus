<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class Metrics
{
    /**
     * Counting must never break what is being counted, so failures are ignored.
     */
    public static function increment(string $metric): void
    {
        try {
            DB::table('daily_metrics')->upsert(
                ['metric_date' => now()->toDateString(), 'metric' => $metric, 'value' => 1],
                ['metric_date', 'metric'],
                ['value' => DB::raw('value + 1')],
            );
        } catch (Throwable) {
            // Deliberately silent: this also runs while reporting errors.
        }
    }

    public static function today(string $metric): int
    {
        return (int) DB::table('daily_metrics')->where('metric_date', now()->toDateString())->where('metric', $metric)->value('value');
    }
}
