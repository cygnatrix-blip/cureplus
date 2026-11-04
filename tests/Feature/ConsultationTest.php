<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Jobs\SendPrescriptionEmail;
use App\Mail\PrescriptionMail;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DoctorProfile;
use App\Models\FollowUp;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitAttachment;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    /**
     * @return array{Organization, Location, User, Patient, Visit}
     */
    private function consultation(): array
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = User::factory()->create(['name' => 'Dr Rohan']);
        $this->addMember($organization, ['doctor'], $doctor, $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test']);
        $visit = Visit::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id,
            'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
        ]);

        return [$organization, $location, $doctor, $patient, $visit];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rx(array $overrides = []): array
    {
        return $overrides + [
            'items_json' => json_encode([['medicine_name' => 'Paracetamol 500 mg', 'dosage' => '1-0-1', 'timing' => 'after_food', 'duration_days' => 5]]),
            'instructions_language' => 'en',
        ];
    }

    public function test_starting_twice_reopens_the_same_visit_and_updates_the_queue(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = User::factory()->create();
        $this->addMember($organization, ['doctor'], $doctor, $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);

        $this->actingAs($doctor, 'web');
        $this->post(route('app.visits.start', $appointment))->assertRedirect();
        $this->post(route('app.visits.start', $appointment))->assertRedirect();

        $this->assertSame(1, Visit::withoutTenancy()->count());
        $this->assertSame(AppointmentStatus::InConsultation, Appointment::withoutTenancy()->find($appointment->id)->status);
    }

    public function test_clinical_notes_are_encrypted_at_rest(): void
    {
        [, , $doctor, , $visit] = $this->consultation();

        $this->actingAs($doctor, 'web')->put(route('app.visits.update', $visit), ['diagnosis' => 'Secret Diagnosis', 'complaints' => 'Private complaint'])->assertRedirect();

        $row = DB::table('visits')->find($visit->id);
        $this->assertStringNotContainsString('Secret', (string) $row->diagnosis);
        $this->assertStringNotContainsString('Private', (string) $row->complaints);
        $this->get(route('app.visits.show', $visit))->assertOk()->assertSee('Secret Diagnosis');
        $this->assertStringNotContainsString('Secret', json_encode(AuditLog::withoutTenancy()->where('subject_type', 'Visit')->get()));
    }

    public function test_a_nurse_records_vitals_but_cannot_read_notes(): void
    {
        [$organization, $location, , , $visit] = $this->consultation();
        $visit->forceFill(['diagnosis' => 'Secret Diagnosis'])->saveQuietly();
        $nurse = $this->addMember($organization, ['nurse'], location: $location);

        $this->actingAs($nurse, 'web');

        $this->post(route('app.visits.vitals', $visit), ['pulse' => 72, 'spo2' => 98])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('app.visits.show', $visit))->assertOk()->assertDontSee('Secret Diagnosis');
        $this->put(route('app.visits.update', $visit), ['diagnosis' => 'x'])->assertForbidden();
        $this->from(route('app.visits.show', $visit))->post(route('app.visits.vitals', $visit), ['spo2' => 500])->assertSessionHasErrors('spo2');
    }

    public function test_a_receptionist_cannot_open_a_consultation(): void
    {
        [$organization, $location, , , $visit] = $this->consultation();

        $this->actingAs($this->addMember($organization, ['receptionist'], location: $location), 'web')
            ->get(route('app.visits.show', $visit))->assertForbidden();
    }

    public function test_one_doctor_cannot_edit_another_doctors_consultation(): void
    {
        [$organization, $location, , , $visit] = $this->consultation();
        $other = $this->addMember($organization, ['doctor'], location: $location);

        $this->actingAs($other, 'web');

        $this->put(route('app.visits.update', $visit), ['diagnosis' => 'x'])->assertForbidden();
        $this->get(route('app.prescriptions.edit', $visit))->assertForbidden();
    }

    public function test_a_completed_consultation_is_read_only(): void
    {
        [, , $doctor, , $visit] = $this->consultation();
        $this->actingAs($doctor, 'web');

        $this->post(route('app.visits.complete', $visit))->assertRedirect();

        $this->put(route('app.visits.update', $visit), ['diagnosis' => 'changed'])->assertStatus(422);
        $this->put(route('app.prescriptions.update', $visit), $this->rx())->assertStatus(422);
    }

    public function test_attachments_are_private_type_checked_and_need_a_signed_link(): void
    {
        Storage::fake('local');
        [, , $doctor, , $visit] = $this->consultation();
        $this->actingAs($doctor, 'web');

        $this->from(route('app.visits.show', $visit))->post(route('app.visits.attachments.store', $visit), [
            'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'), 'category' => 'other',
        ])->assertSessionHasErrors('file');

        $this->post(route('app.visits.attachments.store', $visit), [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'), 'category' => 'lab_report',
        ])->assertSessionHasNoErrors();

        $attachment = VisitAttachment::withoutTenancy()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringNotContainsString('report', $attachment->path);

        $this->get(route('app.attachments.show', $attachment))->assertForbidden();

        $signed = URL::temporarySignedRoute('app.attachments.show', now()->addMinutes(5), ['attachment' => $attachment->id]);
        $this->get($signed)->assertOk();

        $this->travel(6)->minutes();
        $this->get($signed)->assertForbidden();
    }

    public function test_a_signed_link_does_not_open_a_file_for_another_organization(): void
    {
        Storage::fake('local');
        [$organization, $location, , , $visit] = $this->consultation();
        $attachment = VisitAttachment::withoutTenancy()->create([
            'organization_id' => $organization->id, 'visit_id' => $visit->id, 'disk' => 'local', 'path' => 'x.pdf',
            'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
        ]);
        [, $stranger] = $this->organizationWithOwner();
        $signed = URL::temporarySignedRoute('app.attachments.show', now()->addMinutes(5), ['attachment' => $attachment->id]);

        $this->actingAs($stranger, 'web')->get($signed)->assertNotFound();
        $this->assertNotNull($location);
    }

    public function test_a_prescription_records_the_doctors_details_as_they_were(): void
    {
        [, , $doctor, , $visit] = $this->consultation();
        $profile = DoctorProfile::query()->create(['user_id' => $doctor->id, 'qualification' => 'MBBS', 'registration_number' => 'WB-1234']);
        $this->actingAs($doctor, 'web');

        $this->put(route('app.prescriptions.update', $visit), $this->rx())->assertRedirect();

        $profile->update(['qualification' => 'MBBS, MD', 'registration_number' => 'WB-9999']);

        $prescription = Prescription::withoutTenancy()->firstOrFail();
        $this->assertSame('MBBS', $prescription->doctor_qualification);
        $this->assertSame('WB-1234', $prescription->doctor_registration_number);
        $this->get(route('app.prescriptions.show', $prescription))->assertOk()->assertSee('WB-1234')->assertSee('After food');
    }

    public function test_a_prescription_needs_a_medicine_and_valid_rows(): void
    {
        [, , $doctor, , $visit] = $this->consultation();
        $this->actingAs($doctor, 'web');

        $this->from(route('app.prescriptions.edit', $visit))->put(route('app.prescriptions.update', $visit), $this->rx(['items_json' => '[]']))
            ->assertSessionHasErrors(['items_json' => 'Add at least one medicine.']);
        $this->from(route('app.prescriptions.edit', $visit))->put(route('app.prescriptions.update', $visit), $this->rx(['items_json' => json_encode([['medicine_name' => '']])]))
            ->assertSessionHasErrors('items_json');
        $this->from(route('app.prescriptions.edit', $visit))->put(route('app.prescriptions.update', $visit), $this->rx(['items_json' => 'not json']))
            ->assertSessionHasErrors('items_json');
    }

    public function test_saving_again_replaces_items_and_the_follow_up_follows_the_date(): void
    {
        [$organization, , $doctor, , $visit] = $this->consultation();
        $this->actingAs($doctor, 'web');
        $date = now()->addDays(7)->toDateString();

        $this->put(route('app.prescriptions.update', $visit), $this->rx(['follow_up_on' => $date]));
        $this->put(route('app.prescriptions.update', $visit), $this->rx(['follow_up_on' => $date]));

        app(TenantContext::class)->set($organization);
        $this->assertSame(1, Prescription::query()->firstOrFail()->items()->count());
        $this->assertSame(1, FollowUp::query()->where('status', 'pending')->count());
        app(TenantContext::class)->clear();

        $this->put(route('app.prescriptions.update', $visit), $this->rx());

        $this->assertSame(0, FollowUp::withoutTenancy()->where('status', 'pending')->count());
    }

    public function test_copy_last_loads_the_previous_prescription(): void
    {
        [$organization, $location, $doctor, $patient, $visit] = $this->consultation();
        $this->actingAs($doctor, 'web')->put(route('app.prescriptions.update', $visit), $this->rx());
        $next = Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);

        $this->get(route('app.prescriptions.edit', $next))->assertOk()->assertDontSee('Paracetamol');
        $this->get(route('app.prescriptions.edit', ['visit' => $next, 'copy_last' => 1]))->assertOk()->assertSee('Paracetamol');
    }

    public function test_pdf_download_and_email_work(): void
    {
        Bus::fake();
        [, , $doctor, , $visit] = $this->consultation();
        $this->actingAs($doctor, 'web')->put(route('app.prescriptions.update', $visit), $this->rx());
        $prescription = Prescription::withoutTenancy()->firstOrFail();

        $pdf = $this->get(route('app.prescriptions.pdf', $prescription))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->post(route('app.prescriptions.email', $prescription))->assertSessionHas('success');
        Bus::assertDispatched(SendPrescriptionEmail::class);
    }

    public function test_the_email_job_restores_the_tenant_and_attaches_the_pdf(): void
    {
        Mail::fake();
        [$organization, , $doctor, , $visit] = $this->consultation();
        $this->actingAs($doctor, 'web')->put(route('app.prescriptions.update', $visit), $this->rx());
        $prescription = Prescription::withoutTenancy()->firstOrFail();

        SendPrescriptionEmail::dispatchSync($organization->id, $prescription->id);

        Mail::assertSent(PrescriptionMail::class, fn (PrescriptionMail $mail) => $mail->hasTo('p@example.test') && count($mail->attachments()) === 1);
    }

    public function test_medicine_search_puts_favourites_first_and_ignores_inactive(): void
    {
        [$organization, , $doctor] = $this->consultation();
        Medicine::factory()->create(['generic_name' => 'Paracetamol', 'brand_name' => 'Aaa', 'strength' => '500 mg']);
        $favourite = Medicine::factory()->create(['generic_name' => 'Paracetamol', 'brand_name' => 'Zzz', 'strength' => '650 mg']);
        Medicine::factory()->create(['generic_name' => 'Paracetamol', 'brand_name' => 'Old', 'is_active' => false]);
        $this->actingAs($doctor, 'web');

        $this->post(route('app.medicines.favourite'), ['medicine_id' => $favourite->id])->assertRedirect();

        $this->getJson(route('app.medicines.search', ['q' => 'para']))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $favourite->id)
            ->assertJsonPath('0.favourite', true);
        $this->assertNotNull($organization);
    }

    public function test_changing_a_registration_number_clears_the_verified_badge(): void
    {
        [, , $doctor] = $this->consultation();
        $this->actingAs($doctor, 'web');
        $this->post(route('app.doctor-profile.update'), ['registration_number' => 'WB-1'])->assertSessionHasNoErrors();
        $profile = DoctorProfile::query()->firstOrFail();
        $profile->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();

        $this->post(route('app.doctor-profile.update'), ['registration_number' => 'WB-1', 'qualification' => 'MBBS']);
        $this->assertSame('verified', $profile->fresh()->verification_status);

        $this->post(route('app.doctor-profile.update'), ['registration_number' => 'WB-2']);
        $this->assertSame('unverified', $profile->fresh()->verification_status);
    }
}
