<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Models\DoctorProfile;
use App\Models\Medicine;
use App\Models\Template;
use App\Models\User;
use App\Support\Specialties;
use Database\Seeders\MedicineCatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpecialtyAndCatalogueTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function doctorSignup(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'solo_practice', 'name' => 'Asha Nair', 'email' => 'asha@example.test', 'mobile' => '9812345678',
            'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1', 'address_line' => '12 Park Road', 'city' => 'Kochi',
            'registration_number' => 'AY-123', 'registration_council' => 'National Commission for Indian System of Medicine', 'qualification' => 'BAMS', 'specialty' => 'Ayurveda',
        ];
    }

    public function test_every_kind_of_doctor_on_the_list_can_register(): void
    {
        foreach (['General Physician', 'Dentist', 'Gynecologist', 'Pediatrician', 'Dermatologist', 'Orthopedic', 'ENT', 'Ophthalmologist', 'Psychiatrist', 'Cardiologist', 'Neurologist', 'Urologist', 'Gastroenterologist', 'Oncologist', 'Physiotherapist', 'Dietitian', 'Ayurveda', 'Homeopathy', 'Unani', 'Siddha', 'Yoga & Naturopathy'] as $specialty) {
            $this->assertContains($specialty, Specialties::allowed());
        }

        $this->get(route('register'))->assertOk()->assertSee('Yoga &amp; Naturopathy', false)->assertSee('<optgroup label="Allied Health">', false);
        $this->post(route('register.store'), $this->doctorSignup())->assertSessionHasNoErrors();

        $this->assertSame('Ayurveda', DoctorProfile::query()->firstOrFail()->specialty);
    }

    public function test_a_doctor_must_choose_a_specialty_from_the_list(): void
    {
        $this->from(route('register'))->post(route('register.store'), $this->doctorSignup(['specialty' => '']))->assertSessionHasErrors(['specialty' => 'Please choose your specialty.']);
        $this->from(route('register'))->post(route('register.store'), $this->doctorSignup(['specialty' => '<b>Anything</b>']))->assertSessionHasErrors('specialty');
    }

    public function test_a_clinic_owner_who_sees_no_patients_is_not_asked_for_a_specialty(): void
    {
        $this->post(route('register.store'), $this->doctorSignup(['type' => 'clinic', 'organization_name' => 'City Clinic', 'specialty' => '', 'registration_number' => '', 'registration_council' => '', 'qualification' => '', 'document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')]))->assertSessionDoesntHaveErrors('specialty');
    }

    public function test_a_specialty_from_before_the_list_does_not_block_saving_a_profile(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        DoctorProfile::query()->create(['user_id' => $doctor->id, 'specialty' => 'Sports Medicine']);
        $this->actingAs($doctor, 'web');

        $this->get(route('app.account'))->assertOk()->assertSee('<option value="Sports Medicine" selected>', false);
        $this->post(route('app.doctor-profile.update'), ['specialty' => 'Sports Medicine', 'qualification' => 'MD'])->assertSessionHasNoErrors();
        $this->post(route('app.doctor-profile.update'), ['specialty' => 'Astrology'])->assertSessionHasErrors('specialty');
    }

    public function test_my_account_keeps_the_practice_menu(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->get(route('app.account'))->assertOk()->assertSee('Patients')->assertSee($organization->name);
    }

    public function test_my_account_still_opens_for_a_suspended_practice_without_its_menu(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

        $this->actingAs($owner, 'web')->get(route('app.account'))->assertOk()->assertSee('Change password')->assertDontSee(route('app.patients.index'));
    }

    public function test_my_account_still_opens_for_someone_with_no_practice(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->get(route('app.account'))->assertOk()->assertSee('Change password');
    }

    public function test_my_account_shows_doctor_details_when_the_doctor_role_is_at_another_practice(): void
    {
        [$first, $user, $location] = $this->organizationWithOwner();
        [$second] = $this->organizationWithOwner();
        $this->addMember($second, ['doctor'], $user);

        $this->actingAs($user, 'web')->withSession(['active_organization_id' => $first->id])->get(route('app.account'))->assertOk()->assertSee('Doctor details')->assertSee($first->name);
    }

    public function test_upgrading_moves_old_names_onto_the_list(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        DoctorProfile::query()->create(['user_id' => $doctor->id, 'specialty' => 'Dentist']);
        $template = Template::withoutTenancy()->create(['kind' => 'emr', 'specialty' => 'Dentist', 'name' => 'Check-up', 'body' => []]);
        $migration = require database_path('migrations/2026_11_05_201038_add_online_consultations_and_catalogue_groups.php');

        $migration->down();
        DB::table('doctor_profiles')->where('user_id', $doctor->id)->update(['specialty' => 'General Medicine']);
        DB::table('templates')->where('id', $template->id)->update(['specialty' => 'Pediatrics']);
        $migration->up();

        $this->assertSame('General Physician', DoctorProfile::query()->where('user_id', $doctor->id)->value('specialty'));
        $this->assertSame('Pediatrician', $template->fresh()->specialty);
    }

    public function test_the_catalogue_covers_every_listed_department_and_fits_its_columns(): void
    {
        $rows = collect(MedicineCatalogueSeeder::rows());

        foreach (['General Physician', 'Dentist', 'Gynecologist', 'Pediatrician', 'Dermatologist', 'Orthopedic', 'ENT', 'Ophthalmologist', 'Psychiatrist', 'Cardiologist', 'Neurologist', 'Urologist', 'Gastroenterologist', 'Oncologist', 'Physiotherapist', 'Dietitian', 'Ayurveda', 'Homeopathy', 'Unani', 'Siddha', 'Yoga & Naturopathy'] as $specialty) {
            $this->assertTrue($rows->contains('specialty', $specialty), "No medicines for {$specialty}.");
        }

        $this->assertSame([], $rows->reject(fn (array $row) => Specialties::systemOf($row['specialty']) !== null)->pluck('specialty')->unique()->values()->all(), 'Each medicine belongs to a listed specialty.');
        $this->assertSame([], $rows->filter(fn (array $row) => mb_strlen($row['generic_name']) > 255 || mb_strlen((string) $row['strength']) > 60 || mb_strlen($row['form']) > 40 || blank($row['form']))->values()->all());
    }

    public function test_seeding_twice_adds_nothing_and_keeps_what_an_admin_added(): void
    {
        $existing = Medicine::query()->create(['generic_name' => 'Paracetamol', 'strength' => '500 mg', 'form' => 'Tablet', 'manufacturer' => 'Kept']);
        $branded = Medicine::query()->create(['generic_name' => 'Paracetamol', 'brand_name' => 'Calpol', 'strength' => '500 mg', 'form' => 'Tablet']);

        $this->seed(MedicineCatalogueSeeder::class);
        $count = Medicine::query()->count();
        $this->seed(MedicineCatalogueSeeder::class);

        $this->assertSame($count, Medicine::query()->count());
        $this->assertSame(count(MedicineCatalogueSeeder::rows()) + 1, $count);
        $this->assertSame('General Physician', $existing->fresh()->specialty);
        $this->assertSame('Kept', $existing->fresh()->manufacturer);
        $this->assertNull($branded->fresh()->specialty);
    }

    public function test_search_finds_any_part_of_a_combination_and_a_strength(): void
    {
        $combination = Medicine::factory()->create(['generic_name' => 'Amoxicillin + Clavulanic Acid', 'brand_name' => null, 'strength' => '500 mg + 125 mg']);
        $low = Medicine::factory()->create(['generic_name' => 'Paracetamol', 'brand_name' => null, 'strength' => '500 mg']);
        $high = Medicine::factory()->create(['generic_name' => 'Paracetamol', 'brand_name' => null, 'strength' => '650 mg']);

        $this->assertSame([$combination->id], Medicine::query()->search('clav')->pluck('id')->all());
        $this->assertSame([$high->id], Medicine::query()->search('para 650')->pluck('id')->all());
        $this->assertNotContains($low->id, Medicine::query()->search('para 650')->pluck('id'));
        $this->assertSame([], Medicine::query()->search('moxi')->pluck('id')->all(), 'A word must match from its start.');
    }

    public function test_a_doctor_sees_their_own_system_first_and_can_still_find_the_rest(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        DoctorProfile::query()->create(['user_id' => $doctor->id, 'specialty' => 'Ayurveda']);
        Medicine::factory()->create(['generic_name' => 'Aspirin', 'brand_name' => null, 'system' => 'Allopathy', 'specialty' => 'Cardiologist']);
        Medicine::factory()->create(['generic_name' => 'Ashwagandha Churna', 'brand_name' => null, 'system' => 'Ayurveda', 'specialty' => 'Ayurveda']);

        $rows = $this->actingAs($doctor, 'web')->getJson(route('app.medicines.search', ['q' => 'as']))->assertOk()->json();

        $this->assertSame(['Ashwagandha Churna', 'Aspirin'], array_column($rows, 'generic'));
    }

    public function test_the_catalogue_can_be_browsed_by_system_and_department(): void
    {
        [, $owner] = $this->organizationWithOwner();
        Medicine::factory()->create(['generic_name' => 'Aspirin', 'brand_name' => null, 'system' => 'Allopathy', 'specialty' => 'Cardiologist']);
        Medicine::factory()->create(['generic_name' => 'Ashwagandha Churna', 'brand_name' => null, 'system' => 'Ayurveda', 'specialty' => 'Ayurveda']);
        $this->actingAs($owner, 'web');

        $this->get(route('app.medicines.index', ['specialty' => 'Cardiologist']))->assertOk()->assertSee('Aspirin')->assertDontSee('Ashwagandha');
        $this->get(route('app.medicines.index', ['system' => 'Ayurveda']))->assertOk()->assertSee('Ashwagandha')->assertDontSee('Aspirin');
        $this->get(route('app.medicines.index', ['q' => 'asp', 'specialty' => 'not-a-department']))->assertOk()->assertSee('Aspirin');
    }

    public function test_an_admin_import_files_a_medicine_under_its_department(): void
    {
        $this->actingAs($this->superAdmin(), 'platform');
        $file = UploadedFile::fake()->createWithContent('medicines.csv', "generic_name,brand_name,strength,form,manufacturer,specialty\nNewcillin,Newbrand,250 mg,Tablet,Acme,Cardiologist\nOldcillin,,100 mg,Tablet,,Astrology\n");

        $this->post(route('admin.medicines.import'), ['file' => $file])->assertSessionHas('success');

        $this->assertSame('Allopathy', Medicine::query()->where('generic_name', 'Newcillin')->value('system'));
        $this->assertNull(Medicine::query()->where('generic_name', 'Oldcillin')->value('specialty'));
    }
}
