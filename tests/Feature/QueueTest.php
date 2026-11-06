<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class QueueTest extends TestCase
{
    /**
     * @return array{User, Location, Appointment, Patient, User}
     */
    private function queued(AppointmentStatus $status = AppointmentStatus::CheckedIn): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = User::factory()->create(['name' => 'Dr Queue']);
        $this->addMember($organization, ['doctor'], $doctor, $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Private Patient Name']);
        $appointment = Appointment::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id,
            'doctor_user_id' => $doctor->id, 'status' => $status, 'token_number' => 7,
        ]);

        return [$owner, $location, $appointment, $patient, $doctor];
    }

    public function test_the_board_shows_todays_queue_and_a_move_updates_it(): void
    {
        [$owner, , $appointment] = $this->queued(AppointmentStatus::Booked);
        $this->actingAs($owner, 'web');

        $this->get(route('app.queue.index'))->assertOk()->assertSee('Private Patient Name');
        $this->get(route('app.queue.index', ['fragment' => 1]))->assertOk()->assertDontSee('<html', false);

        $this->postJson(route('app.queue.move', $appointment), ['status' => 'checked_in'])->assertOk()->assertJson(['status' => 'checked_in']);

        $this->assertSame(AppointmentStatus::CheckedIn, Appointment::withoutTenancy()->find($appointment->id)->status);
    }

    public function test_unfinished_appointments_from_earlier_days_stay_on_the_board_until_closed(): void
    {
        [$owner, , $appointment] = $this->queued(AppointmentStatus::InConsultation);
        $appointment->forceFill(['scheduled_on' => today()->subDays(2)])->saveQuietly();
        $this->actingAs($owner, 'web');

        $this->get(route('app.queue.index'))->assertOk()
            ->assertSee('Not finished from earlier days')
            ->assertSee('Private Patient Name')
            ->assertSee(today()->subDays(2)->format('d M'));

        $appointment->forceFill(['status' => AppointmentStatus::Completed])->saveQuietly();

        $this->get(route('app.queue.index'))->assertOk()->assertDontSee('Not finished from earlier days')->assertSee('The queue is empty');
    }

    public function test_impossible_moves_are_refused(): void
    {
        [$owner, , $appointment] = $this->queued(AppointmentStatus::Completed);

        $this->actingAs($owner, 'web')->postJson(route('app.queue.move', $appointment), ['status' => 'checked_in'])->assertStatus(422);
    }

    public function test_a_no_show_gives_the_slot_back(): void
    {
        [$owner, , $appointment] = $this->queued(AppointmentStatus::Booked);
        $appointment->forceFill(['slot_key' => '202701010900'])->saveQuietly();

        $this->actingAs($owner, 'web')->postJson(route('app.queue.move', $appointment), ['status' => 'no_show'])->assertOk();

        $this->assertNull(Appointment::withoutTenancy()->find($appointment->id)->slot_key);
    }

    public function test_the_tv_display_needs_no_login_and_never_shows_a_patient_name(): void
    {
        [, $location, $appointment, , $doctor] = $this->queued(AppointmentStatus::InConsultation);

        $token = $location->fresh()->display_token ?? Location::withoutTenancy()->find($location->id)->display_token;

        $this->get(route('display.show', $token))->assertOk()->assertDontSee('Private Patient Name');
        $this->getJson(route('display.data', $token))
            ->assertOk()
            ->assertJsonPath('doctors.0.name', $doctor->name)
            ->assertJsonPath('doctors.0.now', $appointment->token_number)
            ->assertDontSee('Private Patient Name');
        $this->get(route('display.manifest', $token))->assertOk()->assertJsonPath('display', 'fullscreen');
    }

    public function test_a_wrong_display_token_is_404(): void
    {
        $this->get(route('display.show', 'nope'))->assertNotFound();
        $this->getJson(route('display.data', 'nope'))->assertNotFound();
    }

    public function test_a_receptionist_cannot_start_a_consultation(): void
    {
        [, $location, $appointment] = $this->queued();
        $receptionist = $this->addMember($location->organization()->withoutGlobalScopes()->firstOrFail(), ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web')->post(route('app.visits.start', $appointment))->assertForbidden();
    }
}
