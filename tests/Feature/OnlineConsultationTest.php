<?php

namespace Tests\Feature;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Jobs\SendMessage;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\DoctorProfile;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnlineConsultationTest extends TestCase
{
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('next monday 10:00');
    }

    /**
     * @return array{Organization, Location, User, User}
     */
    private function clinic(bool $offersVideo = true, ?string $meetLink = null): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $organization->forceFill(['slug' => 'city-clinic'])->save();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        DoctorProfile::query()->create(['user_id' => $doctor->id, 'offers_video' => $offersVideo, 'video_link' => $meetLink]);
        DoctorAvailability::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'weekday' => $this->monday->dayOfWeek, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 15]);

        return [$organization, $location, $doctor, $owner];
    }

    private function patient(Organization $organization): Patient
    {
        return Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi Kumar', 'mobile' => '+919812345678', 'email' => 'ravi@example.test']);
    }

    private function book(Patient $patient, User $doctor, Location $location, AppointmentMode $mode = AppointmentMode::Online): Appointment
    {
        return app(AppointmentService::class)->bookSlot($patient, $doctor->id, $location->id, $this->monday, VisitType::New, mode: $mode);
    }

    public function test_a_patient_can_choose_a_video_call_and_gets_a_private_room(): void
    {
        [, $location, $doctor] = $this->clinic();

        $this->get(route('booking.show', ['city-clinic', 'location' => $location->id, 'doctor' => $doctor->id, 'date' => $this->monday->toDateString()]))->assertOk()->assertSee('How would you like to meet?');
        $this->post(route('book.choose', 'city-clinic'), ['location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'starts_at' => $this->monday->format('Y-m-d H:i'), 'mode' => 'online'])->assertRedirect(route('book.details', 'city-clinic'));
        $this->get(route('book.details', 'city-clinic'))->assertOk()->assertSee('Video call');
        $this->post(route('book.otp', 'city-clinic'), ['name' => 'Ravi', 'email' => 'ravi@example.test', 'mobile' => '9812345678', 'consent' => '1'])->assertRedirect();
        $this->post(route('book.confirm', 'city-clinic'), ['code' => $this->lastEmailedCode()])->assertRedirect();

        $appointment = Appointment::withoutTenancy()->firstOrFail();
        $this->assertSame(AppointmentMode::Online, $appointment->mode);
        $this->assertMatchesRegularExpression('#^https://meet\.jit\.si/CurePlus[A-Za-z0-9]{32}$#', $appointment->video_link);
    }

    public function test_each_jitsi_appointment_gets_its_own_room(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $first = $this->book($this->patient($organization), $doctor, $location);
        $second = app(AppointmentService::class)->bookSlot($this->patient($organization), $doctor->id, $location->id, $this->monday->addMinutes(15), VisitType::New, mode: AppointmentMode::Online);

        $this->assertNotSame($first->video_link, $second->video_link);
    }

    public function test_a_doctor_who_has_not_turned_on_video_cannot_be_booked_for_it(): void
    {
        [$organization, $location, $doctor, $owner] = $this->clinic(offersVideo: false);

        $this->get(route('booking.show', ['city-clinic', 'location' => $location->id, 'doctor' => $doctor->id, 'date' => $this->monday->toDateString()]))->assertOk()->assertDontSee('How would you like to meet?');
        $this->post(route('book.choose', 'city-clinic'), ['location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'starts_at' => $this->monday->format('Y-m-d H:i'), 'mode' => 'online'])->assertStatus(422);

        $this->actingAs($owner, 'web')->post(route('app.appointments.store'), ['patient_id' => $this->patient($organization)->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'starts_at' => $this->monday->format('Y-m-d H:i'), 'mode' => 'online'])->assertSessionHasErrors('mode');
        $this->assertSame(0, Appointment::withoutTenancy()->count());
    }

    public function test_staff_can_book_a_video_call_and_a_walk_in_stays_at_the_clinic(): void
    {
        [$organization, $location, $doctor, $owner] = $this->clinic();
        $patient = $this->patient($organization);
        $this->actingAs($owner, 'web');

        $this->post(route('app.appointments.store'), ['patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'starts_at' => $this->monday->format('Y-m-d H:i'), 'mode' => 'online', 'video_consent' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(Appointment::withoutTenancy()->firstOrFail()->isOnline());

        $this->post(route('app.appointments.store'), ['patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'walk_in' => '1', 'mode' => 'online'])->assertRedirect(route('app.queue.index'));

        $walkIn = Appointment::withoutTenancy()->where('kind', 'walk_in')->firstOrFail()->refresh();
        $this->assertSame(AppointmentMode::InPerson, $walkIn->mode);
        $this->assertNull($walkIn->video_link);
    }

    public function test_a_video_call_holds_the_same_slot_as_a_clinic_visit(): void
    {
        [$organization, $location, $doctor, $owner] = $this->clinic();
        $this->book($this->patient($organization), $doctor, $location);

        $this->actingAs($owner, 'web')->post(route('app.appointments.store'), ['patient_id' => $this->patient($organization)->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'starts_at' => $this->monday->format('Y-m-d H:i')])->assertSessionHasErrors('starts_at');
    }

    public function test_a_doctors_own_google_meet_link_is_used_instead_of_jitsi(): void
    {
        [$organization, $location, $doctor] = $this->clinic(meetLink: 'https://meet.google.com/abc-defg-hij');

        $this->assertSame('https://meet.google.com/abc-defg-hij', $this->book($this->patient($organization), $doctor, $location)->video_link);
    }

    public function test_a_doctor_turns_on_video_and_saves_a_clean_meet_link(): void
    {
        [, , $doctor] = $this->clinic(offersVideo: false);
        $this->actingAs($doctor, 'web');

        $this->post(route('app.doctor-profile.update'), ['offers_video' => '1', 'video_link' => ' https://meet.google.com/abc-defg-hij?authuser=0 '])->assertSessionHasNoErrors();

        $profile = DoctorProfile::query()->where('user_id', $doctor->id)->firstOrFail();
        $this->assertTrue((bool) $profile->offers_video);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $profile->video_link);
    }

    public function test_only_a_google_meet_link_can_be_saved(): void
    {
        [, , $doctor] = $this->clinic();

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.update'), ['video_link' => 'https://evil.example/meet.google.com/abc-defg-hij'])->assertSessionHasErrors(['video_link' => 'Please paste a Google Meet link, like https://meet.google.com/abc-defg-hij.']);
    }

    public function test_the_patient_is_sent_the_link_and_is_not_told_to_come_in(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        Queue::fake();

        $appointment = $this->book($this->patient($organization), $doctor, $location);

        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'video_consult' && $job->channel === 'email' && $job->variables['link'] === $appointment->video_link);
        Queue::assertNotPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'appointment_booked');

        $appointment->update(['status' => AppointmentStatus::CheckedIn]);
        app(AppointmentService::class)->transition($appointment, AppointmentStatus::InConsultation);

        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'video_consult_now' && $job->variables['link'] === $appointment->video_link);
        Queue::assertNotPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'token_called');
    }

    public function test_a_clinic_visit_keeps_its_usual_messages(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $this->clinicsHaveOwnEmail();
        Queue::fake();

        $this->book($this->patient($organization), $doctor, $location, AppointmentMode::InPerson);

        Queue::assertPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'appointment_booked');
        Queue::assertNotPushed(SendMessage::class, fn (SendMessage $job) => $job->event === 'video_consult');
    }

    public function test_only_the_treating_doctor_gets_the_join_button_and_staff_can_send_the_link(): void
    {
        [$organization, $location, $doctor, $owner] = $this->clinic();
        $appointment = $this->book($this->patient($organization), $doctor, $location);
        $url = route('app.appointments.index', ['date' => $this->monday->toDateString()]);

        $this->actingAs($doctor, 'web')->get($url)->assertOk()->assertSee('Join video');
        $this->actingAs($owner, 'web')->get($url)->assertOk()->assertDontSee('Join video')->assertSee('wa.me', false)->assertSee(rawurlencode($appointment->video_link), false);
    }

    public function test_the_consultation_and_prescription_pages_follow_the_mode(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $patient = $this->patient($organization);
        $appointment = $this->book($patient, $doctor, $location);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'appointment_id' => $appointment->id, 'visited_on' => today()]);
        $inPerson = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]);
        $this->actingAs($doctor, 'web');

        $this->get(route('app.visits.show', $visit))->assertOk()->assertSee('Join video');
        $this->get(route('app.prescriptions.edit', $visit))->assertOk()->assertSee('Telemedicine Practice Guidelines');
        $this->get(route('app.prescriptions.edit', $inPerson))->assertOk()->assertDontSee('Telemedicine Practice Guidelines');
    }

    public function test_the_patient_can_join_until_the_appointment_is_over(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $account = PatientAccount::factory()->create();
        $patient = $this->patient($organization);
        $patient->forceFill(['patient_account_id' => $account->id])->save();
        $appointment = $this->book($patient, $doctor, $location);
        $this->actingAs($account, 'patient');

        $this->get(route('patient.appointments.show', $appointment->id))->assertOk()->assertSee('Join video call')->assertSee($appointment->video_link, false);

        foreach ([AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow] as $status) {
            $appointment->update(['status' => $status]);

            $this->get(route('patient.appointments.show', $appointment->id))->assertOk()->assertDontSee('Join video call')->assertDontSee($appointment->video_link, false);
        }
    }

    public function test_another_patient_cannot_see_the_room(): void
    {
        [$organization, $location, $doctor] = $this->clinic();
        $appointment = $this->book($this->patient($organization), $doctor, $location);

        $this->actingAs(PatientAccount::factory()->create(), 'patient')->get(route('patient.appointments.show', $appointment->id))->assertNotFound();
    }

    public function test_the_queue_offers_no_check_in_for_a_video_call(): void
    {
        [$organization, $location, $doctor, $owner] = $this->clinic();
        $patient = $this->patient($organization);
        Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::Booked, 'scheduled_on' => today(), 'mode' => AppointmentMode::Online, 'video_link' => 'https://meet.jit.si/CurePlusRoom']);

        $this->actingAs($owner, 'web')->get(route('app.queue.index', ['fragment' => 1]))->assertOk()->assertSee($patient->name)->assertDontSee('Check in');
    }
}
