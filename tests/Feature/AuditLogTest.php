<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\Csv;
use App\Tenancy\TenantContext;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_audit_logs_cannot_be_changed_or_deleted(): void
    {
        $log = app(AuditLogger::class)->record('test.action');

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Update should be refused.');
        } catch (LogicException) {
            $this->assertSame('test.action', $log->fresh()->action ?? AuditLog::withoutTenancy()->find($log->id)->action);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_secrets_are_redacted_whatever_the_caller_passes(): void
    {
        $log = app(AuditLogger::class)->record('test.action', metadata: [
            'password' => 'hunter2', 'nested' => ['api_secret' => 'abc', 'otp_code' => '123456'], 'reason' => 'kept',
        ]);

        $stored = AuditLog::withoutTenancy()->findOrFail($log->id)->metadata;

        $this->assertSame('[redacted]', $stored['password']);
        $this->assertSame('[redacted]', $stored['nested']['api_secret']);
        $this->assertSame('[redacted]', $stored['nested']['otp_code']);
        $this->assertSame('kept', $stored['reason']);
    }

    public function test_updates_store_field_names_not_values(): void
    {
        [, $owner, $location] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->put(route('app.locations.update', $location), ['name' => 'Private New Name', 'is_active' => '1']);

        $log = AuditLog::withoutTenancy()->where('action', 'locations.updated')->firstOrFail();
        $this->assertContains('name', $log->metadata['fields']);
        $this->assertStringNotContainsString('Private New Name', json_encode($log->metadata));
    }

    public function test_an_owner_sees_only_their_own_organizations_log(): void
    {
        [$a, $ownerA] = $this->organizationWithOwner();
        [$b] = $this->organizationWithOwner();
        AuditLog::withoutTenancy()->create(['organization_id' => $a->id, 'action' => 'mine.action', 'created_at' => now()]);
        AuditLog::withoutTenancy()->create(['organization_id' => $b->id, 'action' => 'theirs.action', 'created_at' => now()]);
        AuditLog::withoutTenancy()->create(['organization_id' => null, 'action' => 'platform.action', 'created_at' => now()]);

        $this->actingAs($ownerA, 'web');

        $this->get(route('app.audit.index'))->assertOk()->assertSee('mine.action')->assertDontSee('theirs.action')->assertDontSee('platform.action');

        $csv = $this->get(route('app.audit.export'))->streamedContent();
        $this->assertStringContainsString('mine.action', $csv);
        $this->assertStringNotContainsString('theirs.action', $csv);
    }

    public function test_a_platform_event_records_no_organization(): void
    {
        $log = app(AuditLogger::class)->record('platform.event');

        $this->assertNull($log->organization_id);
        $this->assertFalse(app(TenantContext::class)->has());
    }

    public function test_spreadsheet_formulas_in_exports_are_neutralised(): void
    {
        foreach (['=HYPERLINK("x")', '+1+1', '-1', '@SUM(A1)'] as $dangerous) {
            $this->assertStringStartsWith("'", Csv::safe($dangerous));
        }

        $this->assertSame('Ordinary name', Csv::safe('Ordinary name'));
    }
}
