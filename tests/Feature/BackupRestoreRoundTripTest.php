<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Services\Backups\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A real restore drops and recreates every table, which cannot happen inside the
 * transaction the other tests run in, so this class opts out and rebuilds afterwards.
 */
class BackupRestoreRoundTripTest extends TestCase
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

    public function test_a_restore_brings_back_what_was_lost_and_removes_what_came_after(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create(['name' => "O'Brien \"Clinic\"; DROP TABLE users; --"]);
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => "Line one\nLine two রবি", 'allergies' => 'Penicillin']);
        $service = app(BackupService::class);

        $run = $service->run('manual');

        // Disaster, followed by new data that the restore should remove.
        Patient::withoutTenancy()->whereKey($patient->id)->forceDelete();
        DB::table('locations')->where('id', $location->id)->update(['name' => 'Changed after the backup']);
        $later = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Registered after the backup']);

        $this->assertSame(0, Artisan::call('cureplus:backup-restore', ['id' => $run->id, '--force' => true]));

        $restored = Patient::withoutTenancy()->findOrFail($patient->id);
        $this->assertSame("Line one\nLine two রবি", $restored->name);
        $this->assertSame('Penicillin', $restored->allergies, 'Encrypted values still decrypt.');
        $this->assertSame("O'Brien \"Clinic\"; DROP TABLE users; --", Organization::query()->findOrFail($organization->id)->name);
        $this->assertNotSame('Changed after the backup', DB::table('locations')->where('id', $location->id)->value('name'));
        $this->assertNull(Patient::withoutTenancy()->find($later->id));

        // The app still works against the restored database.
        $this->assertSame(0, Artisan::call('migrate:status'));
    }

    public function test_the_restore_command_refuses_an_unknown_backup(): void
    {
        $this->assertSame(1, Artisan::call('cureplus:backup-restore', ['id' => 999999, '--force' => true]));
    }
}
