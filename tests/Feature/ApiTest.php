<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTest extends TestCase
{
    private function key(?Organization $organization, array $scopes, array $attributes = []): string
    {
        $token = 'cpk_'.Str::random(48);
        $key = ApiKey::withoutTenancy()->create(['organization_id' => $organization?->id, 'name' => 'Test', 'prefix' => substr($token, 0, 10), 'key_hash' => hash('sha256', $token), 'scopes' => $scopes]);
        $key->forceFill($attributes)->save();

        return $token;
    }

    private function prescription(Organization $organization): Prescription
    {
        $location = Location::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();
        $doctor = User::factory()->create();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi', 'abha_id' => '12345678901234']);
        $visit = Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr X', 'issued_at' => now()]);
        PrescriptionItem::withoutTenancy()->create(['organization_id' => $organization->id, 'prescription_id' => $prescription->id, 'medicine_name' => 'Paracetamol', 'dosage' => '1-0-1']);

        return $prescription;
    }

    public function test_keys_are_required_and_scoped(): void
    {
        Medicine::factory()->create(['generic_name' => 'Paracetamol']);

        $this->getJson('/api/v1/medicines')->assertStatus(401);
        $this->withToken('cpk_wrong')->getJson('/api/v1/medicines')->assertStatus(401);
        $this->withToken($this->key(null, ['prescriptions.read']))->getJson('/api/v1/medicines')->assertStatus(403);
        $this->withToken($this->key(null, ['catalogue.read']))->getJson('/api/v1/medicines?q=Para')->assertOk()->assertJsonPath('data.0.generic_name', 'Paracetamol');
    }

    public function test_revoked_and_expired_keys_stop_working(): void
    {
        $this->withToken($this->key(null, ['catalogue.read'], ['revoked_at' => now()]))->getJson('/api/v1/medicines')->assertStatus(401);
        $this->withToken($this->key(null, ['catalogue.read'], ['expires_at' => now()->subDay()]))->getJson('/api/v1/medicines')->assertStatus(401);
    }

    public function test_a_prescription_comes_back_as_fhir_and_the_read_is_audited(): void
    {
        [$organization] = $this->organizationWithOwner();
        $prescription = $this->prescription($organization);

        $response = $this->withToken($this->key($organization, ['prescriptions.read']))->getJson("/api/v1/prescriptions/{$prescription->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'Bundle');
        $response->assertJsonPath('entry.0.resource.resourceType', 'Patient');
        $response->assertJsonPath('entry.0.resource.identifier.1.value', '12345678901234');
        $response->assertJsonPath('entry.2.resource.medicationCodeableConcept.text', 'Paracetamol');
        $this->assertSame(1, AuditLog::withoutTenancy()->where('action', 'prescriptions.read_by_api')->where('organization_id', $organization->id)->count());
    }

    public function test_a_key_cannot_read_another_organizations_prescription(): void
    {
        [$mine] = $this->organizationWithOwner();
        [$other] = $this->organizationWithOwner();
        $prescription = $this->prescription($other);

        $this->withToken($this->key($mine, ['prescriptions.read']))->getJson("/api/v1/prescriptions/{$prescription->id}")->assertNotFound();
    }

    public function test_a_platform_key_cannot_read_prescriptions(): void
    {
        [$organization] = $this->organizationWithOwner();
        $prescription = $this->prescription($organization);

        $this->withToken($this->key(null, ['prescriptions.read']))->getJson("/api/v1/prescriptions/{$prescription->id}")->assertForbidden();
    }

    public function test_a_suspended_organizations_key_stops_working(): void
    {
        [$organization] = $this->organizationWithOwner();
        $token = $this->key($organization, ['catalogue.read']);
        $organization->forceFill(['status' => 'suspended'])->save();

        $this->withToken($token)->getJson('/api/v1/medicines')->assertStatus(401);
    }

    public function test_an_admin_issues_a_key_shown_once_and_can_revoke_it(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');

        $response = $this->post(route('admin.api-keys.store'), ['name' => 'Pharmacy', 'scopes' => ['catalogue.read']])->assertSessionHas('new_api_key');
        $token = session('new_api_key');
        $key = ApiKey::withoutTenancy()->firstOrFail();

        $this->assertSame(hash('sha256', $token), $key->key_hash);
        $this->get(route('admin.api-keys.index'))->assertOk()->assertDontSee($key->key_hash);
        $this->assertNotNull($response);

        $this->post(route('admin.api-keys.revoke', $key->id))->assertSessionHas('success');
        $this->withToken($token)->getJson('/api/v1/medicines')->assertStatus(401);
    }

    public function test_an_unknown_scope_is_refused(): void
    {
        $this->actingAs($this->superAdmin(), 'platform')->from(route('admin.api-keys.index'))
            ->post(route('admin.api-keys.store'), ['name' => 'x', 'scopes' => ['everything']])
            ->assertSessionHasErrors('scopes.0');
    }
}
