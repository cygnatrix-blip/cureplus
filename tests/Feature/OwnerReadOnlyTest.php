<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Tests\TestCase;

class OwnerReadOnlyTest extends TestCase
{
    /**
     * @return array{Visit, User, User, Appointment}
     */
    private function consultation(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::CheckedIn]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'appointment_id' => $appointment->id, 'visited_on' => today(), 'diagnosis' => 'Written by the doctor']);

        return [$visit, $owner, $doctor, $appointment];
    }

    public function test_an_owner_can_read_a_doctors_notes_but_sees_no_way_to_change_them(): void
    {
        [$visit, $owner] = $this->consultation();

        $this->actingAs($owner, 'web')->get(route('app.visits.show', $visit))->assertOk()
            ->assertSee('Written by the doctor')
            ->assertDontSee('Save notes')
            ->assertDontSee('Save vitals')
            ->assertDontSee('Write prescription');
    }

    public function test_an_owner_cannot_change_anything_in_the_consultation(): void
    {
        [$visit, $owner] = $this->consultation();
        $this->actingAs($owner, 'web');

        $this->put(route('app.visits.update', $visit), ['diagnosis' => 'Changed by the owner'])->assertForbidden();
        $this->post(route('app.visits.vitals', $visit), ['pulse' => 80])->assertForbidden();
        $this->post(route('app.visits.complete', $visit))->assertForbidden();
        $this->post(route('app.visits.attachments.store', $visit), [])->assertForbidden();

        $this->assertSame('Written by the doctor', Visit::withoutTenancy()->find($visit->id)->diagnosis);
        $this->assertTrue(Visit::withoutTenancy()->find($visit->id)->isOpen());
    }

    public function test_the_treating_doctor_can_still_edit(): void
    {
        [$visit, , $doctor] = $this->consultation();

        $this->actingAs($doctor, 'web')->put(route('app.visits.update', $visit), ['diagnosis' => 'Corrected by the doctor'])->assertRedirect();

        $this->assertSame('Corrected by the doctor', Visit::withoutTenancy()->find($visit->id)->diagnosis);
    }

    public function test_an_owner_who_is_the_treating_doctor_can_edit_their_own(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        Membership::withoutTenancy()->where('user_id', $owner->id)->firstOrFail()->roles()->syncWithoutDetaching(Role::query()->where('key', 'doctor')->pluck('id'));
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $owner->id, 'visited_on' => today()]);

        $this->actingAs($owner, 'web')->put(route('app.visits.update', $visit), ['diagnosis' => 'Own note'])->assertRedirect();
        $this->post(route('app.visits.vitals', $visit), ['pulse' => 80])->assertRedirect();

        $this->assertSame('Own note', Visit::withoutTenancy()->find($visit->id)->diagnosis);
    }

    public function test_an_owner_cannot_start_a_consultation_or_move_the_queue(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::CheckedIn]);

        $this->actingAs($owner, 'web')->from(route('app.queue.index'))->post(route('app.visits.start', $appointment))->assertSessionHas('error');

        $this->assertSame(0, Visit::withoutTenancy()->count());
        $this->assertSame(AppointmentStatus::CheckedIn, Appointment::withoutTenancy()->find($appointment->id)->status);
    }

    public function test_an_owner_pressing_start_on_one_already_begun_just_views_it(): void
    {
        [$visit, $owner, , $appointment] = $this->consultation();

        $this->actingAs($owner, 'web')->post(route('app.visits.start', $appointment))->assertRedirect(route('app.visits.show', $visit));

        $this->assertSame(AppointmentStatus::CheckedIn, Appointment::withoutTenancy()->find($appointment->id)->status);
    }

    public function test_a_nurse_can_still_record_vitals(): void
    {
        [$visit] = $this->consultation();
        $organization = Organization::query()->findOrFail($visit->organization_id);
        $nurse = $this->addMember($organization, ['nurse'], location: Location::withoutTenancy()->findOrFail($visit->location_id));

        $this->actingAs($nurse, 'web')->post(route('app.visits.vitals', $visit), ['pulse' => 80])->assertRedirect();
    }
}
