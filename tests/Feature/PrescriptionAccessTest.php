<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Tests\TestCase;

class PrescriptionAccessTest extends TestCase
{
    private function prescriptionFor(int $organizationId, int $locationId, User $doctor, string $patientName): Prescription
    {
        $patient = Patient::factory()->create(['organization_id' => $organizationId, 'name' => $patientName]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organizationId, 'location_id' => $locationId, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]);

        return Prescription::withoutTenancy()->create(['organization_id' => $organizationId, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $locationId, 'doctor_user_id' => $doctor->id, 'doctor_name' => $doctor->name, 'issued_at' => now()]);
    }

    public function test_an_owner_sees_every_prescription_and_can_search(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $first = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr First']), $location);
        $second = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Second']), $location);
        $this->prescriptionFor($organization->id, $location->id, $first, 'Ravi Kumar');
        $this->prescriptionFor($organization->id, $location->id, $second, 'Sita Devi');
        $this->actingAs($owner, 'web');

        $this->get(route('app.dashboard'))->assertSee('Prescriptions');
        $this->get(route('app.prescriptions.index'))->assertOk()->assertSee('Ravi Kumar')->assertSee('Sita Devi');
        $this->get(route('app.prescriptions.index', ['q' => 'Ravi']))->assertSee('Ravi Kumar')->assertDontSee('Sita Devi');
        $this->get(route('app.prescriptions.index', ['doctor' => $second->id]))->assertSee('Sita Devi')->assertDontSee('Ravi Kumar');
        $this->get(route('app.prescriptions.index', ['from' => today()->addDay()->toDateString()]))->assertDontSee('Ravi Kumar');
    }

    public function test_a_doctor_lists_only_their_own(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $first = $this->addMember($organization, ['doctor'], location: $location);
        $second = $this->addMember($organization, ['doctor'], location: $location);
        $this->prescriptionFor($organization->id, $location->id, $first, 'Ravi Kumar');
        $this->prescriptionFor($organization->id, $location->id, $second, 'Sita Devi');

        $this->actingAs($first, 'web')->get(route('app.prescriptions.index'))->assertOk()->assertSee('Ravi Kumar')->assertDontSee('Sita Devi');
    }

    public function test_reception_and_nurses_cannot_open_prescriptions(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $prescription = $this->prescriptionFor($organization->id, $location->id, $doctor, 'Ravi Kumar');

        foreach (['receptionist', 'nurse', 'accountant'] as $role) {
            $this->actingAs($this->addMember($organization, [$role], location: $location), 'web');
            $this->get(route('app.prescriptions.index'))->assertForbidden();
            $this->get(route('app.prescriptions.show', $prescription))->assertForbidden();
            $this->get(route('app.prescriptions.pdf', $prescription))->assertForbidden();
            $this->post(route('app.prescriptions.share', $prescription))->assertForbidden();
        }
    }

    public function test_an_owner_can_choose_to_let_reception_print(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);
        $prescription = $this->prescriptionFor($organization->id, $location->id, $doctor, 'Ravi Kumar');

        $this->actingAs($owner, 'web')->put(route('app.roles.update'), ['grants' => ['receptionist' => ['dashboard.view', 'prescriptions.view']]])->assertSessionHasNoErrors();
        auth('web')->logout();

        $this->actingAs($receptionist, 'web');
        $this->get(route('app.prescriptions.show', $prescription))->assertOk();
        $this->put(route('app.prescriptions.update', $prescription->visit_id), ['items_json' => '[]'])->assertForbidden();
    }

    public function test_another_clinics_prescription_stays_invisible(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        [, $otherOwner] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $prescription = $this->prescriptionFor($organization->id, $location->id, $doctor, 'Ravi Kumar');

        $this->actingAs($otherOwner, 'web');
        $this->get(route('app.prescriptions.index'))->assertOk()->assertDontSee('Ravi Kumar');
        $this->get(route('app.prescriptions.show', $prescription))->assertNotFound();
    }
}
