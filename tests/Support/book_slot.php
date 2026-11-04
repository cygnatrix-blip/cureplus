<?php

/*
 * A separate OS process for ConcurrentBookingTest: each one has its own database
 * connection, so the row lock is really contended. Prints "booked" or "taken".
 */

use App\Enums\VisitType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Patient;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $patientId, $doctorId, $locationId, $startsAt, $releaseAt] = $argv;

// All children wait for the same moment, to make the race as tight as possible.
time_sleep_until((float) $releaseAt);

$patient = Patient::withoutTenancy()->findOrFail((int) $patientId);

try {
    app(AppointmentService::class)->bookSlot($patient, (int) $doctorId, (int) $locationId, CarbonImmutable::parse($startsAt), VisitType::New);
    echo 'booked';
} catch (SlotUnavailableException) {
    echo 'taken';
}
