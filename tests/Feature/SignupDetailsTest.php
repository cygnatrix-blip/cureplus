<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Services\OrganizationVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignupDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function doctor(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'solo_practice', 'name' => 'Rohan Roy', 'email' => 'rohan@example.test', 'mobile' => '9812345678',
            'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1',
            'address_line' => '12 Park Road', 'city' => 'Kolkata', 'pincode' => '700016',
            'registration_number' => 'WB-12345', 'registration_council' => 'West Bengal Medical Council', 'qualification' => 'MBBS, MD', 'specialty' => 'General Physician',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clinic(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'clinic', 'organization_name' => 'City Clinic', 'name' => 'Asha Sen', 'email' => 'asha@example.test', 'mobile' => '9812345679',
            'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1',
            'address_line' => '12 Park Road', 'city' => 'Kolkata',
            'document' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        ];
    }

    public function test_a_doctors_signup_is_ready_for_review_straight_away(): void
    {
        $this->post('/register', $this->doctor())->assertRedirect();

        $organization = Organization::query()->firstOrFail();
        $this->assertTrue(app(OrganizationVerification::class)->isComplete($organization), 'Nobody should need to be called back.');

        $location = Location::withoutTenancy()->firstOrFail();
        $this->assertSame('12 Park Road', $location->address_line);
        $this->assertSame('700016', $location->pincode);
        $profile = DoctorProfile::query()->firstOrFail();
        $this->assertSame('WB-12345', $profile->registration_number);
        $this->assertSame('MBBS, MD', $profile->qualification);
        $this->assertSame('unverified', $profile->verification_status);
    }

    public function test_a_clinics_signup_is_ready_for_review_straight_away(): void
    {
        $this->post('/register', $this->clinic())->assertRedirect();

        $organization = Organization::query()->firstOrFail();
        $this->assertTrue(app(OrganizationVerification::class)->isComplete($organization));

        $document = OrganizationDocument::withoutTenancy()->firstOrFail();
        $this->assertSame('clinic_proof', $document->type);
        $this->assertSame('licence.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('verification/'.$organization->id.'/', $document->path);
    }

    public function test_a_clinic_owner_who_sees_patients_is_set_up_as_a_doctor_too(): void
    {
        $this->post('/register', $this->clinic(['owner_is_doctor' => '1']))->assertSessionHasErrors(['registration_number', 'registration_council', 'qualification']);

        $this->post('/register', $this->clinic(['owner_is_doctor' => '1', 'document' => UploadedFile::fake()->create('l.pdf', 200, 'application/pdf'), 'registration_number' => 'WB-9', 'specialty' => 'Cardiologist', 'registration_council' => 'West Bengal Medical Council', 'qualification' => 'MBBS']))->assertRedirect();

        $membership = Membership::withoutTenancy()->with('roles')->firstOrFail();
        $this->assertEqualsCanonicalizing(['owner', 'doctor'], $membership->roles->pluck('key')->all());
        $this->assertNotNull($membership->doctor_slug);
        $this->assertSame('WB-9', DoctorProfile::query()->firstOrFail()->registration_number);
    }

    public function test_a_clinic_owner_who_does_not_see_patients_is_not_asked_for_registration(): void
    {
        $this->post('/register', $this->clinic())->assertSessionHasNoErrors();

        $this->assertSame(0, DoctorProfile::query()->count());
    }

    public function test_nothing_is_created_when_a_required_detail_is_missing(): void
    {
        foreach (['address_line', 'city', 'registration_number', 'registration_council', 'qualification'] as $field) {
            $this->post('/register', $this->doctor([$field => '']))->assertSessionHasErrors($field);
        }

        $this->post('/register', $this->clinic(['document' => null]))->assertSessionHasErrors('document');
        $this->post('/register', $this->doctor(['pincode' => '12']))->assertSessionHasErrors('pincode');

        $this->assertSame(0, Organization::query()->count());
    }

    public function test_a_dangerous_or_oversized_file_is_refused(): void
    {
        $this->post('/register', $this->clinic(['document' => UploadedFile::fake()->create('x.html', 5, 'text/html')]))->assertSessionHasErrors('document');
        $this->post('/register', $this->clinic(['document' => UploadedFile::fake()->create('x.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('document');

        $this->assertSame(0, OrganizationDocument::withoutTenancy()->count());
    }

    public function test_a_doctors_certificate_is_optional_but_kept(): void
    {
        $this->post('/register', $this->doctor(['document' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf')]))->assertRedirect();

        $this->assertSame('doctor_registration', OrganizationDocument::withoutTenancy()->firstOrFail()->type);
    }

    public function test_the_form_asks_for_everything(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('name="address_line"', false)
            ->assertSee('name="registration_number"', false)
            ->assertSee('name="document"', false)
            ->assertSee('multipart/form-data', false)
            ->assertSee('West Bengal Medical Council');
    }

    public function test_approval_needs_the_address_too(): void
    {
        $organization = Organization::factory()->pending()->create();
        Location::factory()->create(['organization_id' => $organization->id, 'address_line' => null]);
        OrganizationDocument::factory()->create(['organization_id' => $organization->id]);

        $this->assertFalse(app(OrganizationVerification::class)->isComplete($organization));
    }
}
