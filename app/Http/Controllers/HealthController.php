<?php

namespace App\Http\Controllers;

use App\Models\SystemHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Public for uptime monitors, so it reports pass or fail and nothing else.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->passes(fn () => DB::select('select 1')),
            'cache' => $this->passes(function () {
                Cache::put('health.ping', 1, 10);

                return Cache::get('health.ping') === 1;
            }),
            'scheduler' => $this->passes(function () {
                $beat = SystemHeartbeat::query()->find('scheduler')?->beat_at;

                return $beat !== null && $beat->diffInSeconds(now()) < config('cureplus.heartbeat.max_age_seconds');
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks], $healthy ? 200 : 503);
    }

    private function passes(callable $check): bool
    {
        try {
            return $check() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
