<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consent;
use App\Models\DoctorAvailability;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('next monday 10:00');
    }

    /**
     * @return array{Organization, Location, User}
     */
    private function clinic(string $slug = 'city-clinic'): array
    {
        $organization = Organization::factory()->create(['slug' => $slug, 'name' => 'City Clinic']);
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $doctor = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Roy']), $location);
        Membership::withoutTenancy()->where('user_id', $doctor->id)->update(['doctor_slug' => 'dr-roy']);
        DoctorAvailability::withoutTenancy()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id,
            'weekday' => $this->monday->dayOfWeek, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 15,
        ]);

        return [$organization, $location, $doctor];
    }

    /**
     * @return array<string, mixed>
     */
    private function slot(Location $location, User $doctor): array
    {
        return ['location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'starts_at' => $this->monday->format('Y-m-d H:i')];
    }

    public function test_organization_and_doctor_links_show_slots(): void
    {
        [, , $doctor] = $this->clinic();

        $this->get('/book/city-clinic?date='.$this->monday->toDateString())->assertOk()->assertSee('City Clinic')->assertSee('10:15 AM');
        $this->get('/city-clinic/dr-roy?date='.$this->monday->toDateString())->assertOk()->assertSee('Dr Roy')->assertSee('10:15 AM');
        $this->assertNotNull($doctor);
    }

    public function test_a_guest_books_with_otp_and_gets_a_portal_account(): void
    {
        [$organization, $location, $doctor] = $this->clinic();

        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $doctor))->assertRedirect(route('book.details', 'city-clinic'));
        $this->from(route('book.details', 'city-clinic'))->post(route('book.otp', 'city-clinic'), ['name' => 'Ravi', 'email' => 'ravi@example.test', 'mobile' => '98123 45678'])->assertSessionHasErrors('consent');
        $this->assertSame(0, $this->emailedCodeCount(), 'No code without consent.');

        $this->post(route('book.otp', 'city-clinic'), ['name' => 'Ravi', 'email' => 'Ravi@Example.test', 'mobile' => '98123 45678', 'age' => 30, 'gender' => 'male', 'consent' => '1'])->assertRedirect();

        $this->assertSame(0, Appointment::withoutTenancy()->count(), 'Nothing is booked before the OTP.');

        $this->from(route('book.details', 'city-clinic'))->post(route('book.confirm', 'city-clinic'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertSame(0, Appointment::withoutTenancy()->count());

        $this->post(route('book.confirm', 'city-clinic'), ['code' => $this->lastEmailedCode()])->assertRedirect();

        $appointment = Appointment::withoutTenancy()->firstOrFail();
        $this->assertSame('link', $appointment->source);
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame($organization->id, (int) $appointment->organization_id);

        $patient = Patient::withoutTenancy()->firstOrFail();
        $account = PatientAccount::query()->firstOrFail();
        $this->assertSame($account->id, (int) $patient->patient_account_id);
        $this->assertAuthenticatedAs($account, 'patient');

        $consent = Consent::query()->firstOrFail();
        $this->assertSame('booking_and_care', $consent->purpose);
        $this->assertSame($organization->id, (int) $consent->grantee_organization_id);
    }

    public function test_knowing_a_patients_name_and_mobile_does_not_give_access_to_their_record(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $victim = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi', 'mobile' => '+919812345678', 'email' => 'real.ravi@example.test']);

        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $doctor));
        $this->post(route('book.otp', 'city-clinic'), ['name' => 'Ravi', 'email' => 'attacker@example.test', 'mobile' => '9812345678', 'consent' => '1']);
        $this->post(route('book.confirm', 'city-clinic'), ['code' => $this->lastEmailedCode()]);

        $fresh = Patient::withoutTenancy()->find($victim->id);
        $this->assertNull($fresh->patient_account_id);
        $this->assertSame('real.ravi@example.test', $fresh->email);
        $this->assertSame(2, Patient::withoutTenancy()->count());
    }

    public function test_a_returning_patient_is_not_registered_twice(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi', 'email' => 'ravi@example.test', 'mobile' => '+919812345678']);

        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $doctor));
        $this->post(route('book.otp', 'city-clinic'), ['name' => 'Ravi', 'email' => 'ravi@example.test', 'mobile' => '9812345678', 'consent' => '1']);
        $this->post(route('book.confirm', 'city-clinic'), ['code' => $this->lastEmailedCode()]);

        $this->assertSame(1, Patient::withoutTenancy()->count());
    }

    public function test_a_signed_in_patient_can_book_for_family_and_not_for_strangers(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $account = PatientAccount::factory()->create(['email' => 'mine@example.test']);
        $mine = Patient::factory()->create(['organization_id' => $organization->id, 'email' => $account->email, 'patient_account_id' => $account->id]);
        $stranger = Patient::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($account, 'patient');
        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $doctor));

        $this->post(route('book.confirm', 'city-clinic'), ['patient_id' => $stranger->id])->assertNotFound();
        $this->post(route('book.confirm', 'city-clinic'), ['patient_id' => $mine->id])->assertRedirect();

        $this->assertSame($mine->id, (int) Appointment::withoutTenancy()->firstOrFail()->patient_id);
    }

    public function test_an_online_booking_blocks_the_slot_for_staff_too(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $account = PatientAccount::factory()->create();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => $account->email, 'patient_account_id' => $account->id]);

        $this->actingAs($account, 'patient');
        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $doctor));
        $this->post(route('book.confirm', 'city-clinic'), ['patient_id' => $patient->id]);

        $this->get('/book/city-clinic?date='.$this->monday->toDateString())->assertOk()->assertDontSee('10:00 AM')->assertSee('10:15 AM');
    }

    public function test_a_taken_slot_gives_a_clear_message(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $account = PatientAccount::factory()->create();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'email' => $account->email, 'patient_account_id' => $account->id]);
        Appointment::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
            'starts_at' => $this->monday, 'ends_at' => $this->monday->addMinutes(15), 'slot_key' => Appointment::slotKey($this->monday), 'status' => AppointmentStatus::Booked,
        ]);

        $this->actingAs($account, 'patient');
        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $doctor));

        $this->post(route('book.confirm', 'city-clinic'), ['patient_id' => $patient->id])->assertRedirect(route('booking.show', 'city-clinic'))->assertSessionHas('error');
    }

    public function test_unknown_pending_and_suspended_organizations_all_look_the_same(): void
    {
        Organization::factory()->pending()->create(['slug' => 'waiting']);
        $suspended = Organization::factory()->create(['slug' => 'closed']);
        $suspended->forceFill(['status' => 'suspended'])->save();

        foreach (['/no-such-clinic', '/waiting', '/closed'] as $path) {
            $this->get($path)->assertNotFound()->assertSee('Page not found');
        }
    }

    public function test_a_doctor_or_location_from_elsewhere_cannot_be_chosen(): void
    {
        [, $location] = $this->clinic();
        [, $otherLocation, $otherDoctor] = $this->clinic('other-clinic');

        $this->post(route('book.choose', 'city-clinic'), $this->slot($location, $otherDoctor))->assertNotFound();
        $this->post(route('book.choose', 'city-clinic'), $this->slot($otherLocation, $otherDoctor))->assertNotFound();
    }

    public function test_real_routes_win_over_booking_slugs(): void
    {
        $this->clinic('login');

        $this->get('/login')->assertOk()->assertDontSee('City Clinic');
    }

    public function test_a_branded_manifest_serves_the_organizations_name_and_colour(): void
    {
        $organization = Organization::factory()->create(['slug' => 'city-clinic', 'name' => 'City Clinic', 'brand_color' => '#112233']);

        $this->get(route('brand.manifest', $organization->slug))->assertOk()
            ->assertJsonPath('name', 'City Clinic')
            ->assertJsonPath('theme_color', '#112233')
            ->assertJsonPath('start_url', '/city-clinic');
        $this->get('/book/city-clinic')->assertOk()->assertSee(route('brand.manifest', 'city-clinic'));
    }

    public function test_the_booking_page_can_show_in_hindi(): void
    {
        $this->clinic();

        $this->withSession(['locale' => 'hi'])->get('/book/city-clinic')->assertOk()->assertSee('अपना अपॉइंटमेंट बुक करें');
    }
}
