<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Tests\TestCase;

class PrescriptionWriterTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function rx(): array
    {
        return ['items_json' => (string) json_encode([['medicine_id' => null, 'medicine_name' => 'Paracetamol', 'dosage' => '1-0-1', 'timing' => 'after_food', 'duration_days' => 3]]), 'instructions_language' => 'en'];
    }

    /**
     * @return array{Visit, User, User, User}
     */
    private function visitWithDoctor(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $treating = $this->addMember($organization, ['doctor'], location: $location);
        $other = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $treating->id, 'visited_on' => today()]);

        return [$visit, $owner, $treating, $other];
    }

    public function test_the_treating_doctor_can_write_it(): void
    {
        [$visit, , $treating] = $this->visitWithDoctor();

        $this->actingAs($treating, 'web');
        $this->get(route('app.prescriptions.edit', $visit))->assertOk();
        $this->put(route('app.prescriptions.update', $visit), $this->rx())->assertRedirect();

        $this->assertSame(1, Prescription::withoutTenancy()->where('visit_id', $visit->id)->count());
    }

    public function test_an_owner_who_is_not_the_treating_doctor_cannot_write_it(): void
    {
        [$visit, $owner] = $this->visitWithDoctor();

        $this->actingAs($owner, 'web');
        $this->get(route('app.prescriptions.edit', $visit))->assertForbidden();
        $this->put(route('app.prescriptions.update', $visit), $this->rx())->assertForbidden();

        $this->assertSame(0, Prescription::withoutTenancy()->count());
    }

    public function test_another_doctor_at_the_same_clinic_cannot_write_it(): void
    {
        [$visit, , , $other] = $this->visitWithDoctor();

        $this->actingAs($other, 'web');
        $this->get(route('app.prescriptions.edit', $visit))->assertForbidden();
        $this->put(route('app.prescriptions.update', $visit), $this->rx())->assertForbidden();
    }

    public function test_an_owner_who_is_the_treating_doctor_can_write_it(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $this->addDoctorRole($organization->id, $owner);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $owner->id, 'visited_on' => today()]);

        $this->actingAs($owner, 'web')->put(route('app.prescriptions.update', $visit), $this->rx())->assertRedirect();

        $this->assertSame(1, Prescription::withoutTenancy()->where('visit_id', $visit->id)->count());
    }

    public function test_someone_given_the_permission_who_is_not_the_doctor_still_cannot(): void
    {
        [$visit, $owner] = $this->visitWithDoctor();
        $receptionist = $this->addMember($visit->organization()->withoutGlobalScopes()->firstOrFail(), ['receptionist']);
        $this->actingAs($owner, 'web')->put(route('app.roles.update'), ['grants' => ['receptionist' => ['dashboard.view', 'prescriptions.manage']]]);
        auth('web')->logout();

        $this->actingAs($receptionist, 'web')->put(route('app.prescriptions.update', $visit), $this->rx())->assertForbidden();
    }

    public function test_the_owner_can_still_read_it(): void
    {
        [$visit, $owner, $treating] = $this->visitWithDoctor();
        $this->actingAs($treating, 'web')->put(route('app.prescriptions.update', $visit), $this->rx());
        $prescription = Prescription::withoutTenancy()->where('visit_id', $visit->id)->firstOrFail();
        auth('web')->logout();

        $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->assertOk()->assertSee('Paracetamol');
    }

    private function addDoctorRole(int $organizationId, User $user): void
    {
        $membership = Membership::withoutTenancy()->where('organization_id', $organizationId)->where('user_id', $user->id)->firstOrFail();
        $membership->roles()->syncWithoutDetaching(Role::query()->where('key', 'doctor')->pluck('id'));
    }
}
