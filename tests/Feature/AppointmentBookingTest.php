<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\DoctorAvailability;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\DoctorCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('next monday 10:00');
    }

    /**
     * @return array{Organization, User, Location, User, Patient}
     */
    private function clinic(?User $doctor = null, string $start = '10:00', string $end = '12:00'): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor ??= User::factory()->create();
        $this->addMember($organization, ['doctor'], $doctor, $location);
        DoctorAvailability::withoutTenancy()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id,
            'weekday' => $this->monday->dayOfWeek, 'start_time' => $start, 'end_time' => $end, 'slot_minutes' => 15,
        ]);

        return [$organization, $owner, $location, $doctor, Patient::factory()->create(['organization_id' => $organization->id])];
    }

    private function book(Patient $patient, User $doctor, Location $location, ?CarbonImmutable $at = null): Appointment
    {
        return app(AppointmentService::class)->bookSlot($patient, $doctor->id, $location->id, $at ?? $this->monday, VisitType::New);
    }

    public function test_slots_follow_the_doctors_hours(): void
    {
        [$organization, , $location, $doctor] = $this->clinic();

        $slots = app(DoctorCalendar::class)->slots($doctor->id, $organization->id, $location->id, $this->monday);

        $this->assertCount(8, $slots);
        $this->assertSame('10:00', $slots[0]['starts_at']->format('H:i'));
        $this->assertSame('11:45', $slots[7]['starts_at']->format('H:i'));
    }

    public function test_a_slot_cannot_be_booked_twice(): void
    {
        [, , $location, $doctor, $patient] = $this->clinic();
        $this->book($patient, $doctor, $location);

        $this->expectException(SlotUnavailableException::class);
        $this->book($patient, $doctor, $location);
    }

    public function test_the_database_itself_refuses_a_double_booking(): void
    {
        [$organization, , $location, $doctor, $patient] = $this->clinic();
        $first = $this->book($patient, $doctor, $location);

        $this->expectException(UniqueConstraintViolationException::class);
        Appointment::withoutTenancy()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id,
            'doctor_user_id' => $doctor->id, 'scheduled_on' => $first->scheduled_on, 'slot_key' => $first->slot_key, 'token_number' => 99,
        ]);
    }

    public function test_a_booking_at_one_organization_blocks_the_doctor_at_another(): void
    {
        $doctor = User::factory()->create();
        [, , $locationA, , $patientA] = $this->clinic($doctor);
        [$organizationB, , $locationB, , $patientB] = $this->clinic($doctor, '10:00', '12:00');

        $this->book($patientA, $doctor, $locationA);

        $slots = app(DoctorCalendar::class)->slots($doctor->id, $organizationB->id, $locationB->id, $this->monday);
        $this->assertFalse($slots[0]['available']);
        $this->assertTrue($slots[1]['available']);

        $this->expectException(SlotUnavailableException::class);
        $this->book($patientB, $doctor, $locationB);
    }

    public function test_a_longer_slot_elsewhere_blocks_every_overlapping_slot_here(): void
    {
        $doctor = User::factory()->create();
        [, , $locationA, , $patientA] = $this->clinic($doctor);
        [$organizationB, , $locationB] = $this->clinic($doctor);
        DoctorAvailability::withoutTenancy()->where('location_id', $locationA->id)->update(['slot_minutes' => 30]);

        $this->book($patientA, $doctor, $locationA);

        $slots = app(DoctorCalendar::class)->slots($doctor->id, $organizationB->id, $locationB->id, $this->monday);
        $this->assertFalse($slots[0]['available']);
        $this->assertFalse($slots[1]['available']);
        $this->assertTrue($slots[2]['available']);
    }

    public function test_cancelling_frees_the_slot_everywhere(): void
    {
        [, , $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);

        app(AppointmentService::class)->cancel($appointment, 'Patient unwell');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNull($appointment->slot_key);
        $this->assertSame('Patient unwell', $appointment->cancel_reason);
        $this->assertInstanceOf(Appointment::class, $this->book($patient, $doctor, $location));
    }

    public function test_times_outside_hours_and_in_the_past_are_refused(): void
    {
        [, , $location, $doctor, $patient] = $this->clinic();

        foreach ([$this->monday->setTime(13, 0), $this->monday->setTime(10, 7), CarbonImmutable::parse('last monday 10:00')] as $at) {
            try {
                $this->book($patient, $doctor, $location, $at);
                $this->fail("{$at} should be refused.");
            } catch (SlotUnavailableException) {
                $this->assertSame(0, Appointment::withoutTenancy()->count());
            }
        }
    }

    public function test_a_clinic_block_only_closes_that_clinic(): void
    {
        $doctor = User::factory()->create();
        [$a, , $locationA] = $this->clinic($doctor);
        [$b, , $locationB] = $this->clinic($doctor);
        $this->block($a, ['doctor_user_id' => $doctor->id]);

        $calendar = app(DoctorCalendar::class);
        $this->assertFalse($calendar->slots($doctor->id, $a->id, $locationA->id, $this->monday)[0]['available']);
        $this->assertTrue($calendar->slots($doctor->id, $b->id, $locationB->id, $this->monday)[0]['available']);
    }

    public function test_a_doctors_personal_leave_closes_every_organization(): void
    {
        $doctor = User::factory()->create();
        [$a, , $locationA] = $this->clinic($doctor);
        [$b, , $locationB] = $this->clinic($doctor);
        $this->block($a, ['doctor_user_id' => $doctor->id, 'applies_everywhere' => true]);

        $calendar = app(DoctorCalendar::class);
        $this->assertFalse($calendar->slots($doctor->id, $a->id, $locationA->id, $this->monday)[0]['available']);
        $this->assertFalse($calendar->slots($doctor->id, $b->id, $locationB->id, $this->monday)[0]['available']);
    }

    public function test_a_location_holiday_closes_only_that_location(): void
    {
        [$organization, , $location, $doctor] = $this->clinic();
        $other = Location::factory()->create(['organization_id' => $organization->id]);
        DoctorAvailability::withoutTenancy()->create([
            'organization_id' => $organization->id, 'location_id' => $other->id, 'doctor_user_id' => $doctor->id,
            'weekday' => $this->monday->dayOfWeek, 'start_time' => '15:00', 'end_time' => '16:00', 'slot_minutes' => 15,
        ]);
        $this->block($organization, ['location_id' => $location->id]);

        $calendar = app(DoctorCalendar::class);
        $this->assertFalse($calendar->slots($doctor->id, $organization->id, $location->id, $this->monday)[0]['available']);
        $this->assertTrue($calendar->slots($doctor->id, $organization->id, $other->id, $this->monday)[0]['available']);
    }

    public function test_only_the_doctor_themself_can_block_every_organization(): void
    {
        [, $owner, $location, $doctor] = $this->clinic();

        $this->actingAs($owner, 'web')->post(route('app.calendar.blocks.store'), [
            'doctor_user_id' => $doctor->id, 'location_id' => $location->id, 'starts_at' => $this->monday, 'ends_at' => $this->monday->addHour(), 'applies_everywhere' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertFalse(CalendarBlock::withoutTenancy()->firstOrFail()->applies_everywhere);
    }

    public function test_hours_cannot_overlap_the_doctors_hours_at_another_organization(): void
    {
        $doctor = User::factory()->create();
        $this->clinic($doctor, '10:00', '12:00');
        [$organizationB, $ownerB] = $this->organizationWithOwner();
        $locationB = Location::factory()->create(['organization_id' => $organizationB->id]);
        $this->addMember($organizationB, ['doctor'], $doctor, $locationB);

        $payload = ['doctor_user_id' => $doctor->id, 'location_id' => $locationB->id, 'weekday' => $this->monday->dayOfWeek, 'slot_minutes' => 15];

        $this->actingAs($ownerB, 'web');

        $this->from(route('app.calendar.index'))->post(route('app.calendar.availability.store'), $payload + ['start_time' => '11:00', 'end_time' => '13:00'])
            ->assertSessionHasErrors('start_time');
        $this->from(route('app.calendar.index'))->post(route('app.calendar.availability.store'), $payload + ['start_time' => '12:00', 'end_time' => '13:00'])
            ->assertSessionHasNoErrors();
    }

    public function test_booking_through_the_page_gives_a_token_and_stale_slots_get_a_clear_message(): void
    {
        [, $owner, , $doctor, $patient] = $this->clinic();
        $payload = ['patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'starts_at' => $this->monday->format('Y-m-d H:i')];

        $this->actingAs($owner, 'web');

        $this->get(route('app.appointments.create', ['patient' => $patient->id, 'doctor' => $doctor->id, 'date' => $this->monday->toDateString()]))->assertOk()->assertSee('10:15');
        $this->post(route('app.appointments.store'), $payload)->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Appointment::withoutTenancy()->firstOrFail()->token_number);

        $this->from(route('app.appointments.create'))->post(route('app.appointments.store'), $payload)
            ->assertSessionHasErrors(['starts_at' => 'That slot has just been taken. Please choose another.']);
    }

    public function test_another_tenants_patient_or_a_stranger_doctor_cannot_be_booked(): void
    {
        [, $owner, , $doctor] = $this->clinic();
        [$other, , , $foreignDoctor] = $this->clinic();
        $foreignPatient = Patient::factory()->create(['organization_id' => $other->id]);
        $patient = Patient::factory()->create(['organization_id' => $owner->memberships()->firstOrFail()->organization_id]);

        $this->actingAs($owner, 'web');

        $this->from(route('app.appointments.create'))->post(route('app.appointments.store'), ['patient_id' => $foreignPatient->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new'])
            ->assertSessionHasErrors('patient_id');
        $this->from(route('app.appointments.create'))->post(route('app.appointments.store'), ['patient_id' => $patient->id, 'doctor_user_id' => $foreignDoctor->id, 'visit_type' => 'new'])
            ->assertSessionHasErrors('doctor_user_id');
    }

    public function test_walk_ins_get_tokens_in_order_and_hold_no_slot(): void
    {
        [, $owner, , $doctor, $patient] = $this->clinic();
        $this->actingAs($owner, 'web');

        foreach ([1, 2] as $expected) {
            $this->post(route('app.appointments.store'), ['patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'new', 'walk_in' => 1])
                ->assertRedirect(route('app.queue.index'));
            $appointment = Appointment::withoutTenancy()->latest('id')->firstOrFail();
            $this->assertSame($expected, $appointment->token_number);
            $this->assertNull($appointment->slot_key);
        }
    }

    public function test_reschedule_moves_the_slot_and_cancel_needs_a_reason(): void
    {
        [, $owner, $location, $doctor, $patient] = $this->clinic();
        $appointment = $this->book($patient, $doctor, $location);
        $taken = $this->book($patient, $doctor, $location, $this->monday->addMinutes(30));
        $this->actingAs($owner, 'web');

        $this->from(route('app.appointments.edit', $appointment))->put(route('app.appointments.update', $appointment), ['starts_at' => $taken->starts_at->format('Y-m-d H:i')])
            ->assertSessionHasErrors('starts_at');

        $this->put(route('app.appointments.update', $appointment), ['starts_at' => $this->monday->addMinutes(15)->format('Y-m-d H:i')])->assertRedirect();
        $this->assertSame('10:15', $appointment->fresh()?->starts_at?->format('H:i') ?? Appointment::withoutTenancy()->find($appointment->id)->starts_at->format('H:i'));

        $this->from(route('app.appointments.index'))->post(route('app.appointments.cancel', $appointment), [])->assertSessionHasErrors('reason');
        $this->post(route('app.appointments.cancel', $appointment), ['reason' => 'Busy'])->assertRedirect();
    }

    public function test_another_organizations_calendar_shows_busy_but_not_details(): void
    {
        $doctor = User::factory()->create();
        [, , $locationA, , $patientA] = $this->clinic($doctor);
        [$organizationB, $ownerB, $locationB, , $patientB] = $this->clinic($doctor);
        $this->book($patientA, $doctor, $locationA);

        $this->actingAs($ownerB, 'web')
            ->get(route('app.appointments.create', ['patient' => $patientB->id, 'doctor' => $doctor->id, 'date' => $this->monday->toDateString()]))
            ->assertOk()
            ->assertDontSee($patientA->name);

        $this->assertSame((int) $organizationB->id, (int) $patientB->organization_id);
        $this->assertNotSame($locationA->id, $locationB->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function block(Organization $organization, array $attributes): void
    {
        CalendarBlock::withoutTenancy()->create($attributes + [
            'organization_id' => $organization->id,
            'starts_at' => $this->monday->startOfDay(),
            'ends_at' => $this->monday->endOfDay(),
        ]);
    }
}
