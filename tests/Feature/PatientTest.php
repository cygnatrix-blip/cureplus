<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientTest extends TestCase
{
    public function test_quick_registration_needs_only_the_basics_and_numbers_per_organization(): void
    {
        [, $ownerA] = $this->organizationWithOwner();
        [, $ownerB] = $this->organizationWithOwner();

        $this->actingAs($ownerA, 'web')->post(route('app.patients.store'), ['name' => 'Ravi Kumar', 'mobile' => '98123 45678', 'age' => 40, 'gender' => 'male'])->assertRedirect();
        $this->actingAs($ownerA, 'web')->post(route('app.patients.store'), ['name' => 'Sita Kumar', 'mobile' => '9812345678', 'age' => 38, 'gender' => 'female'])->assertRedirect();
        $this->actingAs($ownerB, 'web')->post(route('app.patients.store'), ['name' => 'Other', 'mobile' => '9812345678'])->assertRedirect();

        $codes = Patient::withoutTenancy()->orderBy('id')->pluck('patient_code')->all();
        $this->assertSame(['P-000001', 'P-000002', 'P-000001'], $codes);

        $patient = Patient::withoutTenancy()->firstOrFail();
        $this->assertSame('+919812345678', $patient->mobile);
        $this->assertTrue($patient->dob_is_estimated);
        $this->assertSame(40, $patient->ageInYears());
    }

    public function test_a_likely_duplicate_needs_confirming(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $existing = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi Kumar', 'mobile' => '+919812345678']);
        $payload = ['name' => 'Ravi Kumar', 'mobile' => '9812345678'];

        $this->actingAs($owner, 'web')->post(route('app.patients.store'), $payload)->assertOk()->assertSee($existing->patient_code);
        $this->assertSame(1, Patient::withoutTenancy()->count());

        $this->post(route('app.patients.store'), $payload + ['confirm_new' => 1])->assertRedirect();
        $this->assertSame(2, Patient::withoutTenancy()->count());
    }

    public function test_a_family_on_one_mobile_is_not_treated_as_a_duplicate(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $parent = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi', 'mobile' => '+919812345678']);

        $this->actingAs($owner, 'web')->post(route('app.patients.store'), ['name' => 'Asha', 'mobile' => '9812345678'])->assertRedirect();

        $child = Patient::withoutTenancy()->where('name', 'Asha')->firstOrFail();
        $this->get(route('app.patients.show', $parent))->assertOk()->assertSee($child->name);
    }

    public function test_the_same_person_at_two_organizations_stays_two_records(): void
    {
        [, $ownerA] = $this->organizationWithOwner();
        [$b, $ownerB] = $this->organizationWithOwner();
        Patient::factory()->create(['organization_id' => $b->id, 'name' => 'Ravi', 'mobile' => '+919812345678']);

        $this->actingAs($ownerA, 'web')->post(route('app.patients.store'), ['name' => 'Ravi', 'mobile' => '9812345678'])->assertRedirect();

        $this->assertSame(2, Patient::withoutTenancy()->where('mobile', '+919812345678')->count());
    }

    public function test_search_finds_by_name_mobile_id_and_abha(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Unrelated Person', 'mobile' => '+919000000000']);
        $abha = '12345678901234';
        Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi Kumar', 'mobile' => '+919812345678', 'patient_code' => 'P-777777', 'abha_id' => $abha, 'abha_hash' => Patient::hashAbha($abha)]);

        $this->actingAs($owner, 'web');

        foreach (['Ravi', '98123', 'P-777777', '12-3456-7890-1234'] as $term) {
            $this->get(route('app.patients.index', ['q' => $term]))->assertOk()->assertSee('Ravi Kumar')->assertDontSee('Unrelated Person');
        }
    }

    public function test_abha_and_allergies_are_encrypted_at_rest(): void
    {
        [$organization] = $this->organizationWithOwner();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'abha_id' => '12345678901234', 'allergies' => 'Penicillin']);

        $row = DB::table('patients')->find($patient->id);

        $this->assertStringNotContainsString('12345678901234', (string) $row->abha_id);
        $this->assertStringNotContainsString('Penicillin', (string) $row->allergies);
        $this->assertSame('Penicillin', $patient->fresh()->allergies ?? Patient::withoutTenancy()->find($patient->id)->allergies);
    }

    public function test_viewing_a_patient_is_audited_and_receptionists_cannot_see_allergies(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $user = $this->addMember($organization, ['receptionist'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'allergies' => 'Penicillin']);

        $this->actingAs($user, 'web')->get(route('app.patients.show', $patient))->assertOk()->assertDontSee('Penicillin');

        $log = AuditLog::withoutTenancy()->where('action', 'patients.viewed')->firstOrFail();
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame($patient->id, $log->subject_id);
    }

    public function test_another_tenants_patient_is_404_and_invalid_input_is_rejected(): void
    {
        [, $owner] = $this->organizationWithOwner();
        [$other] = $this->organizationWithOwner();
        $foreign = Patient::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($owner, 'web');

        $this->get(route('app.patients.show', $foreign->id))->assertNotFound();
        $this->put(route('app.patients.update', $foreign->id), ['name' => 'x', 'mobile' => '9812345678'])->assertNotFound();
        $this->from(route('app.patients.create'))->post(route('app.patients.store'), ['name' => '', 'mobile' => '123', 'abha_id' => '99'])
            ->assertSessionHasErrors(['name', 'mobile', 'abha_id']);
    }

    public function test_a_nurse_can_view_but_not_edit_patients(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $nurse = $this->addMember($organization, ['nurse'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($nurse, 'web');

        $this->get(route('app.patients.show', $patient))->assertOk();
        $this->get(route('app.patients.create'))->assertForbidden();
        $this->put(route('app.patients.update', $patient), ['name' => 'x', 'mobile' => '9812345678'])->assertForbidden();
    }
}
