<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Races real processes against a real database. SQLite in memory cannot do this,
 * so the test only runs on MariaDB or MySQL.
 */
class ConcurrentBookingTest extends TestCase
{
    /** @var list<string> */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        // Without the wrapping transaction, Laravel also stops reusing the in-memory database,
        // so this class always starts from a fresh migration.
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_parallel_bookings_from_two_organizations_never_double_book_a_doctor(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs a database server with real row locks.');
        }

        $doctor = User::factory()->create();
        $startsAt = CarbonImmutable::parse('next monday 10:00');
        $patients = [];

        // The doctor has overlapping availability at two organizations only in this test,
        // so that both can legitimately offer the very same slot.
        foreach (range(1, 2) as $ignored) {
            $organization = Organization::factory()->create();
            $location = Location::factory()->create(['organization_id' => $organization->id]);
            $this->addMember($organization, ['doctor'], $doctor, $location);
            DoctorAvailability::withoutTenancy()->create([
                'organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id,
                'weekday' => $startsAt->dayOfWeek, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 15,
            ]);
            $patients[] = [Patient::factory()->create(['organization_id' => $organization->id]), $location];
        }

        $releaseAt = microtime(true) + 4;
        $processes = [];

        foreach (range(0, 7) as $index) {
            [$patient, $location] = $patients[$index % 2];
            $command = [PHP_BINARY, base_path('tests/Support/book_slot.php'), $patient->id, $doctor->id, $location->id, $startsAt->toDateTimeString(), $releaseAt];
            $process = proc_open(array_map('strval', $command), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), getenv() + [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => config('database.default'),
                'DB_HOST' => config('database.connections.'.config('database.default').'.host'),
                'DB_PORT' => (string) config('database.connections.'.config('database.default').'.port'),
                'DB_DATABASE' => DB::connection()->getDatabaseName(),
                'DB_USERNAME' => config('database.connections.'.config('database.default').'.username'),
                'DB_PASSWORD' => (string) config('database.connections.'.config('database.default').'.password'),
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
            ]);
            $processes[] = [$process, $pipes];
        }

        $results = [];

        foreach ($processes as [$process, $pipes]) {
            $results[] = trim((string) stream_get_contents($pipes[1]));
            proc_close($process);
        }

        $this->assertSame(1, count(array_keys($results, 'booked', true)), 'Results: '.implode(', ', $results));
        $this->assertSame(7, count(array_keys($results, 'taken', true)), 'Results: '.implode(', ', $results));
        $this->assertSame(1, Appointment::withoutTenancy()->where('doctor_user_id', $doctor->id)->count());
    }
}
