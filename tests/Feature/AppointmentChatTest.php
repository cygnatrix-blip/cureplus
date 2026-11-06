<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Jobs\SendMessage;
use App\Models\Appointment;
use App\Models\AppointmentMessage;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use App\Services\AppointmentChat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppointmentChatTest extends TestCase
{
    /**
     * A clinic with an owner, the appointment's doctor, and a patient with a portal account.
     *
     * @return array{Organization, User, Location, User, Appointment, PatientAccount}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $account = PatientAccount::factory()->create();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test', 'patient_account_id' => $account->id]);
        $appointment = Appointment::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id,
            'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::Booked,
        ]);

        return [$organization, $owner, $location, $doctor, $appointment, $account];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function patientSends(PatientAccount $account, Appointment $appointment, array $data): TestResponse
    {
        return $this->actingAs($account, 'patient')
            ->from(route('patient.appointments.chat', $appointment->id))
            ->post(route('patient.appointments.chat.store', $appointment->id), $data);
    }

    public function test_a_patient_message_reaches_the_doctor_and_is_marked_read_when_opened(): void
    {
        [, , , $doctor, $appointment, $account] = $this->clinic();

        $this->patientSends($account, $appointment, ['body' => 'My fever is back since last night.'])->assertRedirect(route('patient.appointments.chat', $appointment->id));

        $message = AppointmentMessage::withoutTenancy()->sole();
        $this->assertSame(AppointmentMessage::FROM_PATIENT, $message->sender_type);
        $this->assertSame($account->id, $message->sender_account_id);

        $this->actingAs($doctor, 'web')->get(route('app.chats.index'))->assertOk()->assertSee('1 new');
        $this->get(route('app.queue.index'))->assertOk()->assertSee(route('app.appointments.chat', $appointment));
        $this->get(route('app.dashboard'))->assertOk()->assertSee('1 unread message');
        $this->get(route('app.appointments.chat', $appointment))->assertOk()->assertSee('My fever is back since last night.');

        $this->assertNotNull($message->refresh()->read_at);
    }

    public function test_message_text_is_encrypted_at_rest(): void
    {
        [, , , , $appointment, $account] = $this->clinic();

        $this->patientSends($account, $appointment, ['body' => 'Blood sugar 310 this morning']);

        $stored = DB::table('appointment_messages')->value('body');
        $this->assertStringNotContainsString('Blood sugar', $stored);
        $this->assertSame('Blood sugar 310 this morning', AppointmentMessage::withoutTenancy()->sole()->body);
    }

    public function test_a_doctor_reply_alerts_the_patient_by_free_channels_only_and_without_the_text(): void
    {
        [, , , $doctor, $appointment] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        Queue::fake();

        $this->actingAs($doctor, 'web')->post(route('app.appointments.chat.store', $appointment), ['body' => 'Take paracetamol 650 and rest.'])->assertRedirect();

        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'chat_message' && $job->channel === 'email');
        Queue::assertNotPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'chat_message' && in_array($job->channel, ['sms', 'whatsapp'], true));
        Queue::assertNotPushed(SendMessage::class, fn (SendMessage $job) => str_contains(json_encode($job->variables), 'paracetamol'));
    }

    public function test_a_burst_of_replies_sends_one_alert(): void
    {
        [, , , $doctor, $appointment] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        Queue::fake();
        $this->actingAs($doctor, 'web');

        $this->post(route('app.appointments.chat.store', $appointment), ['body' => 'First']);
        $this->post(route('app.appointments.chat.store', $appointment), ['body' => 'Second']);

        Queue::assertPushedTimes(SendMessage::class, 1);
    }

    public function test_files_are_stored_privately_and_reach_only_the_two_people_in_the_chat(): void
    {
        Storage::fake(config('filesystems.default'));
        [, $owner, , $doctor, $appointment, $account] = $this->clinic();

        $this->patientSends($account, $appointment, ['file' => UploadedFile::fake()->create('cbc-report.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();

        $message = AppointmentMessage::withoutTenancy()->sole();
        $this->assertSame('cbc-report.pdf', $message->attachment_name);
        $this->assertStringStartsWith("chat/{$appointment->organization_id}/", $message->attachment_path);
        $this->assertStringNotContainsString('cbc-report', $message->attachment_path);
        Storage::disk(config('filesystems.default'))->assertExists($message->attachment_path);

        $this->get(route('patient.appointments.chat.file', [$appointment->id, $message->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($doctor, 'web')->get(route('app.appointments.chat.file', [$appointment, $message]))->assertOk();
        $this->actingAs($owner, 'web')->get(route('app.appointments.chat.file', [$appointment, $message]))->assertForbidden();
        $this->actingAs(PatientAccount::factory()->create(), 'patient')->get(route('patient.appointments.chat.file', [$appointment->id, $message->id]))->assertNotFound();
    }

    public function test_a_patient_can_send_only_so_many_files_a_day_in_one_chat(): void
    {
        Storage::fake(config('filesystems.default'));
        [, , , , $appointment, $account] = $this->clinic();
        AppointmentMessage::factory()->count(AppointmentChat::FILES_PER_DAY)->create([
            'appointment_id' => $appointment->id, 'attachment_disk' => 'local', 'attachment_path' => 'chat/x.pdf', 'attachment_name' => 'x.pdf',
        ]);

        $this->patientSends($account, $appointment, ['file' => UploadedFile::fake()->create('one-more.pdf', 10, 'application/pdf')])
            ->assertSessionHas('error', 'You can send up to 20 files a day in this chat. Please try again tomorrow.');
        $this->patientSends($account, $appointment, ['body' => 'Text still goes through'])->assertSessionHasNoErrors();

        $this->assertSame(AppointmentChat::FILES_PER_DAY + 1, AppointmentMessage::withoutTenancy()->count());
    }

    public function test_an_empty_message_or_an_unsafe_file_is_refused(): void
    {
        Storage::fake(config('filesystems.default'));
        [, , , , $appointment, $account] = $this->clinic();

        $this->patientSends($account, $appointment, ['body' => ''])->assertSessionHasErrors(['body' => 'Write a message or attach a file.']);
        $this->patientSends($account, $appointment, ['file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors(['file' => 'Send a PDF, JPG or PNG file.']);

        $this->assertSame(0, AppointmentMessage::withoutTenancy()->count());
    }

    public function test_only_the_appointments_own_doctor_can_open_the_chat(): void
    {
        [$organization, $owner, $location, , $appointment] = $this->clinic();
        $otherDoctor = $this->addMember($organization, ['doctor'], location: $location);
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($owner, 'web')->get(route('app.appointments.chat', $appointment))->assertForbidden();
        $this->actingAs($otherDoctor, 'web')->get(route('app.appointments.chat', $appointment))->assertForbidden();
        $this->actingAs($receptionist, 'web')->get(route('app.appointments.chat', $appointment))->assertForbidden();
    }

    public function test_a_doctor_at_another_practice_cannot_find_the_chat(): void
    {
        [, , , , $appointment] = $this->clinic();
        [$otherOrganization, , $otherLocation] = $this->organizationWithOwner();
        $outsider = $this->addMember($otherOrganization, ['doctor'], location: $otherLocation);

        $this->actingAs($outsider, 'web')->get(route('app.appointments.chat', $appointment))->assertNotFound();
    }

    public function test_another_patient_cannot_read_or_write_the_chat(): void
    {
        [, , , , $appointment] = $this->clinic();
        $stranger = PatientAccount::factory()->create();

        $this->actingAs($stranger, 'patient')->get(route('patient.appointments.chat', $appointment->id))->assertNotFound();
        $this->patientSends($stranger, $appointment, ['body' => 'hello'])->assertNotFound();

        $this->assertSame(0, AppointmentMessage::withoutTenancy()->count());
    }

    public function test_a_closed_chat_stays_readable_but_takes_no_messages_until_reopened(): void
    {
        [, , , $doctor, $appointment, $account] = $this->clinic();
        $this->patientSends($account, $appointment, ['body' => 'Thank you, doctor']);

        $this->actingAs($doctor, 'web')->post(route('app.appointments.chat.close', $appointment))->assertRedirect();

        $this->patientSends($account, $appointment, ['body' => 'One more thing'])->assertSessionHas('error', 'This chat is closed.');
        $this->get(route('patient.appointments.chat', $appointment->id))->assertOk()->assertSee('Thank you, doctor')->assertSee('This chat is closed.');
        $this->assertSame(1, AppointmentMessage::withoutTenancy()->count());

        $this->actingAs($doctor, 'web')->post(route('app.appointments.chat.reopen', $appointment));
        $this->patientSends($account, $appointment, ['body' => 'One more thing'])->assertSessionHasNoErrors();

        $this->assertSame(2, AppointmentMessage::withoutTenancy()->count());
    }

    public function test_finished_chats_close_by_themselves_after_thirty_days_but_unfinished_ones_stay_open(): void
    {
        [, , , , $finishedLongAgo] = $this->clinic();
        $finishedLongAgo->forceFill(['status' => AppointmentStatus::Completed, 'scheduled_on' => today()->subDays(31)])->saveQuietly();
        [, , , , $finishedRecently] = $this->clinic();
        $finishedRecently->forceFill(['status' => AppointmentStatus::Completed, 'scheduled_on' => today()->subDays(29)])->saveQuietly();
        [, , , , $neverFinished] = $this->clinic();
        $neverFinished->forceFill(['status' => AppointmentStatus::InConsultation, 'scheduled_on' => today()->subDays(60)])->saveQuietly();

        $this->assertSame(1, app(AppointmentChat::class)->closeStale());

        $this->assertFalse($finishedLongAgo->refresh()->isChatOpen());
        $this->assertTrue($finishedRecently->refresh()->isChatOpen());
        $this->assertTrue($neverFinished->refresh()->isChatOpen());
    }

    public function test_the_patients_data_download_includes_their_messages(): void
    {
        [, , , , $appointment, $account] = $this->clinic();
        $this->patientSends($account, $appointment, ['body' => 'Is it safe to travel?']);

        $this->get(route('patient.privacy.download'))->assertOk()->assertSee('Is it safe to travel?');
    }

    public function test_the_message_list_escapes_what_people_type(): void
    {
        [, , , $doctor, $appointment, $account] = $this->clinic();
        $this->patientSends($account, $appointment, ['body' => '<script>alert("x")</script>']);

        $this->actingAs($doctor, 'web')->get(route('app.appointments.chat.messages', $appointment))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert', false);
    }
}
