<?php

namespace Tests\Feature;

use App\Jobs\RunBackup;
use App\Models\BackupRun;
use App\Models\Organization;
use App\Models\RestoreTest;
use App\Services\Backups\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    public function test_a_backup_is_encrypted_verifiable_and_contains_real_data(): void
    {
        Storage::fake('local');
        Organization::factory()->create(['name' => 'Backup Secret Clinic']);

        $run = app(BackupService::class)->run('manual');

        $this->assertSame('completed', $run->status);
        $this->assertGreaterThan(10, $run->tables_count);
        $contents = Storage::disk('local')->get('backups/'.$run->filename);
        $this->assertStringNotContainsString('Backup Secret Clinic', (string) $contents);
        $this->assertStringNotContainsString('CREATE TABLE', (string) $contents);

        $result = app(BackupService::class)->verify($run);
        $this->assertGreaterThan(10, $result['tables']);
        $this->assertGreaterThan(0, $result['rows']);
    }

    public function test_a_tampered_backup_fails_verification(): void
    {
        Storage::fake('local');
        $run = app(BackupService::class)->run();
        Storage::disk('local')->put('backups/'.$run->filename, 'tampered');

        $this->expectException(\RuntimeException::class);
        app(BackupService::class)->verify($run);
    }

    public function test_a_backup_made_with_another_key_cannot_be_read(): void
    {
        Storage::fake('local');
        $run = app(BackupService::class)->run();
        config(['cureplus.backups.key' => 'a different key']);

        $this->expectException(\RuntimeException::class);
        app(BackupService::class)->verify($run);
    }

    public function test_old_backups_are_pruned_and_recent_ones_kept(): void
    {
        Storage::fake('local');
        $service = app(BackupService::class);
        $old = $service->run();
        $old->forceFill(['created_at' => now()->subDays(config('cureplus.backups.retention_days') + 1)])->save();

        $new = $service->run();

        $this->assertSame('expired', $old->fresh()->status);
        Storage::disk('local')->assertMissing('backups/'.$old->filename);
        Storage::disk('local')->assertExists('backups/'.$new->filename);
    }

    public function test_an_off_site_copy_is_made_when_a_disk_is_configured(): void
    {
        Storage::fake('local');
        Storage::fake('offsite');
        config(['cureplus.backups.offsite_disk' => 'offsite']);

        $run = app(BackupService::class)->run();

        $this->assertTrue($run->copied_offsite);
        Storage::disk('offsite')->assertExists('backups/'.$run->filename);
    }

    public function test_the_admin_can_trigger_verify_download_and_log(): void
    {
        Storage::fake('local');
        Bus::fake();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.backups.store'))->assertSessionHas('success');
        Bus::assertDispatched(RunBackup::class, fn (RunBackup $job) => $job->trigger === 'manual');

        $run = app(BackupService::class)->run('manual');
        $this->get(route('admin.backups.index'))->assertOk()->assertSee($run->filename);
        $this->post(route('admin.backups.verify', $run))->assertSessionHas('success');
        $this->assertSame('passed', RestoreTest::query()->firstOrFail()->result);
        $this->get(route('admin.backups.download', $run))->assertOk();

        $this->post(route('admin.backups.restore-tests'), ['result' => 'passed', 'notes' => 'Restored to staging'])->assertSessionHas('success');
        $this->assertSame(2, RestoreTest::query()->count());
    }

    public function test_only_super_admins_can_reach_backups(): void
    {
        $this->actingAs($this->superAdmin('support'), 'platform')->get(route('admin.backups.index'))->assertForbidden();
    }

    public function test_a_nightly_backup_is_scheduled_as_a_queued_job(): void
    {
        $names = collect(app(Schedule::class)->events())->pluck('description')->all();

        $this->assertContains('backup', $names);
        $this->assertContains('restore-check', $names);
    }

    public function test_a_failed_backup_is_recorded_without_the_error_text(): void
    {
        Storage::fake('local');
        Storage::shouldReceive('disk')->andThrow(new \RuntimeException('secret detail'));

        try {
            app(BackupService::class)->run();
            $this->fail('Should have thrown.');
        } catch (\RuntimeException) {
            $run = BackupRun::query()->firstOrFail();
            $this->assertSame('failed', $run->status);
            $this->assertSame('RuntimeException', $run->error);
        }
    }
}
