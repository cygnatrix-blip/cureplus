<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentMessage;
use App\Models\AuditLog;
use App\Models\DriveAccount;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitAttachment;
use App\Services\Storage\DriveFiles;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GoogleDriveStorageTest extends TestCase
{
    /**
     * Files held by the fake Google Drive, by id.
     *
     * @var array<string, string>
     */
    private array $drive = [];

    /** When set, Google stores different bytes from those sent. */
    private bool $corruptUploads = false;

    /** When set, Google refuses the saved permission. */
    private bool $revoked = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
        Storage::fake('local');
        $this->fakeGoogle();
    }

    private function fakeGoogle(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                str_starts_with($url, 'https://oauth2.googleapis.com/token') => $this->revoked
                    ? Http::response(['error' => 'invalid_grant'], 400)
                    : Http::response(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 3600]),
                str_starts_with($url, 'https://oauth2.googleapis.com/revoke') => Http::response([]),
                str_starts_with($url, 'https://www.googleapis.com/drive/v3/about') => Http::response(['user' => ['emailAddress' => 'Clinic@Gmail.com'], 'storageQuota' => ['limit' => (string) (15 * 1024 ** 3), 'usage' => '1000']]),
                $request->method() === 'POST' && str_starts_with($url, 'https://www.googleapis.com/drive/v3/files') => Http::response(['id' => 'folder-1']),
                $request->method() === 'POST' && str_starts_with($url, 'https://www.googleapis.com/upload/drive/v3/files') => Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&upload_id=u'.count($this->drive)]),
                $request->method() === 'PUT' => $this->store($request->body()),
                str_contains($url, 'alt=media') => isset($this->drive[$this->fileId($url)]) ? Http::response($this->drive[$this->fileId($url)]) : Http::response([], 404),
                isset($this->drive[$this->fileId($url)]) => Http::response(['id' => $this->fileId($url), 'md5Checksum' => md5($this->drive[$this->fileId($url)]), 'trashed' => false]),
                default => Http::response([], 404),
            };
        });
    }

    private function store(string $body): mixed
    {
        $id = 'file-'.(count($this->drive) + 1);
        $this->drive[$id] = $body;

        return Http::response(['id' => $id, 'md5Checksum' => $this->corruptUploads ? md5('other') : md5($body)]);
    }

    private function fileId(string $url): string
    {
        return (string) preg_replace('/\?.*$/', '', basename($url));
    }

    /**
     * @return array{Organization, User, User, Visit}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $visit = Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);

        return [$organization, $owner, $doctor, $visit];
    }

    private function attachment(Visit $visit, string $contents = 'lab report'): VisitAttachment
    {
        $path = 'clinical/'.$visit->organization_id.'/'.uniqid().'.pdf';
        Storage::disk('local')->put($path, $contents);

        return VisitAttachment::withoutTenancy()->create([
            'organization_id' => $visit->organization_id, 'visit_id' => $visit->id, 'disk' => 'local', 'path' => $path,
            'original_name' => 'cbc.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => strlen($contents),
        ]);
    }

    private function connected(Organization $organization, string $email = 'clinic@gmail.com', ?int $total = null, int $used = 0): DriveAccount
    {
        return DriveAccount::withoutTenancy()->create([
            'organization_id' => $organization->id, 'google_email' => $email, 'refresh_token' => 'refresh', 'access_token' => 'access',
            'token_expires_at' => now()->addHour(), 'folder_id' => 'folder-1', 'quota_total' => $total, 'quota_used' => $used, 'quota_checked_at' => now(),
        ]);
    }

    public function test_a_practice_connects_google_drive_through_googles_sign_in(): void
    {
        [$organization, $owner] = $this->clinic();

        $redirect = $this->actingAs($owner, 'web')->get(route('app.integrations.drive.connect'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', (string) $redirect);
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);
        $this->assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
        $this->assertSame('offline', $query['access_type']);

        $this->get(route('app.integrations.drive.callback', ['state' => $query['state'], 'code' => 'abc']))
            ->assertRedirect(route('app.integrations.index'))->assertSessionHas('success');

        $account = DriveAccount::withoutTenancy()->sole();
        $this->assertSame([$organization->id, 'clinic@gmail.com', DriveAccount::ACTIVE, 'refresh'], [$account->organization_id, $account->google_email, $account->status, $account->refresh_token]);
        $this->assertStringNotContainsString('refresh', (string) $account->getRawOriginal('refresh_token'));
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'drive_accounts.connected')->exists());
        $this->get(route('app.integrations.index'))->assertOk()->assertSee('clinic@gmail.com');
    }

    public function test_a_google_answer_without_this_browsers_state_is_refused(): void
    {
        [, $owner] = $this->clinic();

        $this->actingAs($owner, 'web')->get(route('app.integrations.drive.connect'));
        $this->get(route('app.integrations.drive.callback', ['state' => 'forged', 'code' => 'abc']))->assertSessionHas('error');

        $this->assertSame(0, DriveAccount::withoutTenancy()->count());
    }

    public function test_a_nurse_cannot_connect_a_drive(): void
    {
        [$organization] = $this->clinic();
        $nurse = $this->addMember($organization, ['nurse']);

        $this->actingAs($nurse, 'web')->get(route('app.integrations.drive.connect'))->assertForbidden();
    }

    public function test_waiting_files_are_copied_to_the_drive_with_most_room_and_kept_on_the_server(): void
    {
        [$organization, , $doctor, $visit] = $this->clinic();
        $attachment = $this->attachment($visit);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $visit->location_id, 'patient_id' => $visit->patient_id, 'doctor_user_id' => $doctor->id]);
        $message = AppointmentMessage::factory()->create(['appointment_id' => $appointment->id, 'attachment_disk' => 'local', 'attachment_path' => 'chat/x.png', 'attachment_name' => 'rash.png', 'attachment_mime' => 'image/png', 'attachment_size' => 3]);
        Storage::disk('local')->put('chat/x.png', 'png');
        $this->connected($organization, 'small@gmail.com', total: DriveAccount::RESERVE_BYTES + 10);
        $roomy = $this->connected($organization, 'roomy@gmail.com', total: 10 * 1024 ** 3);

        $this->assertSame(2, app(DriveFiles::class)->syncPending());

        $attachment->refresh();
        $this->assertSame([$roomy->id, 'lab report'], [$attachment->drive_account_id, $this->drive[$attachment->drive_file_id]]);
        $this->assertSame($roomy->id, $message->refresh()->drive_account_id);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertNull($attachment->server_removed_at);
    }

    public function test_a_sample_patients_file_is_never_copied(): void
    {
        [$organization, , , $visit] = $this->clinic();
        Patient::withoutTenancy()->whereKey($visit->patient_id)->update(['is_demo' => true]);
        $this->attachment($visit);
        $this->connected($organization);

        $this->assertSame(0, app(DriveFiles::class)->syncPending());
        $this->assertSame([], $this->drive);
    }

    public function test_a_copy_google_stored_differently_is_not_counted(): void
    {
        [$organization, , , $visit] = $this->clinic();
        $attachment = $this->attachment($visit);
        $this->connected($organization);
        $this->corruptUploads = true;

        app(DriveFiles::class)->syncPending();

        $this->assertNull($attachment->refresh()->drive_file_id);
    }

    public function test_a_withdrawn_google_permission_asks_the_practice_to_reconnect(): void
    {
        [$organization, , , $visit] = $this->clinic();
        $attachment = $this->attachment($visit);
        $account = $this->connected($organization);
        $account->forceFill(['token_expires_at' => now()->subMinute()])->save();
        $this->revoked = true;

        app(DriveFiles::class)->syncPending();

        $this->assertSame(DriveAccount::NEEDS_RECONNECT, $account->refresh()->status);
        $this->assertNull($attachment->refresh()->drive_file_id);
    }

    public function test_server_copies_are_removed_only_after_the_practices_agreement_is_recorded_and_files_then_open_from_drive(): void
    {
        [$organization, , $doctor, $visit] = $this->clinic();
        $attachment = $this->attachment($visit, 'blood test');
        $this->connected($organization);
        app(DriveFiles::class)->syncPending();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'platform')->get(route('admin.drive.index'))->assertOk()->assertSee($organization->name)->assertSee('Not asked yet');
        $this->post(route('admin.drive.remove', $organization))->assertSessionHas('error');
        Storage::disk('local')->assertExists($attachment->path);

        $this->post(route('admin.drive.decide', $organization), ['choice' => DriveFiles::REMOVE, 'note' => 'Owner agreed by email on 7 Nov'])->assertSessionHas('success');
        $this->post(route('admin.drive.remove', $organization))->assertSessionHas('success');

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertNotNull($attachment->refresh()->server_removed_at);
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'organizations.drive_server_copy_decided')->exists());

        $signed = URL::temporarySignedRoute('app.attachments.show', now()->addMinutes(5), ['attachment' => $attachment->id]);
        $this->actingAs($doctor, 'web')->get($signed)->assertOk()->assertStreamedContent('blood test');
    }

    public function test_a_server_copy_is_kept_when_drive_no_longer_has_the_file(): void
    {
        [$organization, , , $visit] = $this->clinic();
        $attachment = $this->attachment($visit);
        $this->connected($organization);
        app(DriveFiles::class)->syncPending();
        $this->drive = [];
        $organization->forceFill(['drive_server_copy' => DriveFiles::REMOVE])->save();

        app(DriveFiles::class)->removeServerCopies($organization);

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertNull($attachment->refresh()->drive_file_id);
    }

    public function test_a_drive_holding_the_only_copy_cannot_be_disconnected_until_files_are_brought_back(): void
    {
        [$organization, $owner, , $visit] = $this->clinic();
        $attachment = $this->attachment($visit);
        $account = $this->connected($organization);
        app(DriveFiles::class)->syncPending();
        $organization->forceFill(['drive_server_copy' => DriveFiles::REMOVE])->save();
        app(DriveFiles::class)->removeServerCopies($organization);

        $this->actingAs($owner, 'web')->post(route('app.integrations.drive.disconnect', $account))->assertSessionHas('error');
        $this->assertTrue($account->refresh()->isActive());

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.drive.restore', $organization))->assertSessionHas('success');
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($owner, 'web')->post(route('app.integrations.drive.disconnect', $account))->assertSessionHas('success');
        $this->assertSame([DriveAccount::DISCONNECTED, null], [$account->refresh()->status, $account->refresh_token]);
        $this->assertNull($attachment->refresh()->drive_file_id);
    }

    public function test_another_practices_drive_cannot_be_disconnected(): void
    {
        [$organization] = $this->clinic();
        $account = $this->connected($organization);
        [, $otherOwner] = $this->organizationWithOwner();

        $this->actingAs($otherOwner, 'web')->post(route('app.integrations.drive.disconnect', $account))->assertNotFound();
        $this->assertTrue($account->refresh()->isActive());
    }
}
