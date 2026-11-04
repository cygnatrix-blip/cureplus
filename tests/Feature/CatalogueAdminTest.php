<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\Medicine;
use App\Models\MedicineRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogueAdminTest extends TestCase
{
    public function test_a_clinic_requests_a_medicine_and_an_admin_approves_it(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.medicines.request'), ['generic_name' => 'Newcillin', 'strength' => '250 mg'])->assertSessionHas('success');
        $request = MedicineRequest::withoutTenancy()->firstOrFail();

        $this->actingAs($this->superAdmin(), 'platform');
        $this->get(route('admin.medicines.requests'))->assertOk()->assertSee('Newcillin');
        $this->post(route('admin.medicines.requests.approve', $request->id))->assertRedirect();

        $this->assertSame(1, Medicine::query()->where('generic_name', 'Newcillin')->count());
        $this->assertSame('approved', MedicineRequest::withoutTenancy()->find($request->id)->status);
    }

    public function test_rejecting_needs_a_reason_and_adds_nothing(): void
    {
        [$organization] = $this->organizationWithOwner();
        $request = MedicineRequest::withoutTenancy()->create(['organization_id' => $organization->id, 'generic_name' => 'Badcillin']);
        $this->actingAs($this->superAdmin(), 'platform');

        $this->from(route('admin.medicines.requests'))->post(route('admin.medicines.requests.reject', $request->id), [])->assertSessionHasErrors('reason');
        $this->post(route('admin.medicines.requests.reject', $request->id), ['reason' => 'Duplicate'])->assertRedirect();

        $this->assertSame(0, Medicine::query()->count());
        $this->assertSame('Duplicate', MedicineRequest::withoutTenancy()->find($request->id)->rejection_reason);
    }

    public function test_a_csv_import_adds_rows_without_duplicates(): void
    {
        $csv = "generic_name,brand_name,strength,form,manufacturer\nAmoxicillin,Mox,500 mg,Capsule,Acme\nAmoxicillin,Mox,500 mg,Capsule,Acme\n,,,,\n";
        $this->actingAs($this->superAdmin(), 'platform');

        $this->post(route('admin.medicines.import'), ['file' => UploadedFile::fake()->createWithContent('m.csv', $csv)])->assertSessionHas('success');

        $this->assertSame(1, Medicine::query()->count());
    }

    public function test_clinic_users_and_support_admins_cannot_edit_the_catalogue(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('admin.medicines.store'), ['generic_name' => 'x'])->assertRedirect(route('admin.login'));

        $this->actingAs($this->superAdmin('support'), 'platform')->post(route('admin.medicines.store'), ['generic_name' => 'x'])->assertForbidden();
        $this->assertSame(0, Medicine::query()->count());
    }

    public function test_an_admin_can_verify_a_doctor(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Verified']), $location);
        $profile = DoctorProfile::query()->create(['user_id' => $doctor->id, 'registration_number' => 'WB-1']);
        $this->actingAs($this->superAdmin(), 'platform');

        $this->get(route('admin.doctors.index'))->assertOk()->assertSee('Dr Verified');
        $this->post(route('admin.doctors.verify', $profile), ['status' => 'verified'])->assertRedirect();

        $this->assertSame('verified', $profile->fresh()->verification_status);
        $this->assertNotNull($profile->fresh()->verified_at);
    }
}
