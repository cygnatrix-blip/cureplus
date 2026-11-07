<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DoctorAvailability;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class DoctorCalendarHoursTest extends TestCase
{
    /**
     * @return array{Organization, User, Location, User}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);

        return [$organization, $owner, $location, $doctor];
    }

    private function hours(Organization $organization, Location $location, User $doctor, int $weekday, string $start, string $end, int $slot = 15): DoctorAvailability
    {
        return DoctorAvailability::withoutTenancy()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id,
            'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end, 'slot_minutes' => $slot,
        ]);
    }

    /**
     * @return list<array{int, string, string}>
     */
    private function week(User $doctor): array
    {
        return DoctorAvailability::withoutTenancy()->where('doctor_user_id', $doctor->id)->orderBy('weekday')->orderBy('start_time')->get()
            ->map(fn (DoctorAvailability $row) => [$row->weekday, substr($row->start_time, 0, 5), substr($row->end_time, 0, 5)])->all();
    }

    public function test_the_same_hours_are_added_to_every_day_picked_at_once(): void
    {
        [, $owner, $location, $doctor] = $this->clinic();

        $this->actingAs($owner, 'web')->post(route('app.calendar.availability.store'), [
            'doctor_user_id' => $doctor->id, 'location_id' => $location->id, 'weekdays' => [1, 2, 3], 'start_time' => '10:00', 'end_time' => '13:00', 'slot_minutes' => 15,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Hours added to 3 days.');

        $this->assertSame([[1, '10:00', '13:00'], [2, '10:00', '13:00'], [3, '10:00', '13:00']], $this->week($doctor));
        $this->get(route('app.calendar.index'))->assertOk()->assertSee('Wednesday')->assertSee(route('app.calendar.availability.edit', DoctorAvailability::withoutTenancy()->first()));
    }

    public function test_a_clash_on_one_day_saves_no_day_and_names_the_clash(): void
    {
        [$organization, $owner, $location, $doctor] = $this->clinic();
        $this->hours($organization, $location, $doctor, 2, '12:00', '14:00');

        $this->actingAs($owner, 'web')->from(route('app.calendar.index'))->post(route('app.calendar.availability.store'), [
            'doctor_user_id' => $doctor->id, 'location_id' => $location->id, 'weekdays' => [1, 2], 'start_time' => '10:00', 'end_time' => '13:00', 'slot_minutes' => 15,
        ])->assertSessionHasErrors(['start_time' => 'The doctor already has hours at this time, here or at another organization: Tuesday 10:00–13:00.']);

        $this->assertSame([[2, '12:00', '14:00']], $this->week($doctor));
    }

    public function test_no_day_picked_is_refused(): void
    {
        [, $owner, $location, $doctor] = $this->clinic();

        $this->actingAs($owner, 'web')->from(route('app.calendar.index'))->post(route('app.calendar.availability.store'), [
            'doctor_user_id' => $doctor->id, 'location_id' => $location->id, 'start_time' => '10:00', 'end_time' => '13:00', 'slot_minutes' => 15,
        ])->assertSessionHasErrors(['weekdays' => 'Pick at least one day.']);
    }

    public function test_hours_are_edited_in_place_and_may_overlap_only_themselves(): void
    {
        [$organization, $owner, $location, $doctor] = $this->clinic();
        $morning = $this->hours($organization, $location, $doctor, 1, '10:00', '13:00');
        $this->hours($organization, $location, $doctor, 1, '17:00', '20:00');
        $this->actingAs($owner, 'web');

        $this->get(route('app.calendar.availability.edit', $morning))->assertOk()->assertSee('10:00');
        $this->put(route('app.calendar.availability.update', $morning), ['location_id' => $location->id, 'start_time' => '09:00', 'end_time' => '13:30', 'slot_minutes' => 20])
            ->assertRedirect(route('app.calendar.index'));

        $morning->refresh();
        $this->assertSame(['09:00', '13:30', 20], [substr($morning->start_time, 0, 5), substr($morning->end_time, 0, 5), $morning->slot_minutes]);

        $this->from(route('app.calendar.availability.edit', $morning))->put(route('app.calendar.availability.update', $morning), ['location_id' => $location->id, 'start_time' => '09:00', 'end_time' => '18:00', 'slot_minutes' => 20])
            ->assertSessionHasErrors('start_time');
        $this->assertSame('13:30', substr($morning->refresh()->end_time, 0, 5));
    }

    public function test_a_whole_day_with_morning_and_evening_sessions_is_copied_to_other_days(): void
    {
        [$organization, $owner, $location, $doctor] = $this->clinic();
        $this->hours($organization, $location, $doctor, 1, '10:00', '13:00');
        $this->hours($organization, $location, $doctor, 1, '17:00', '20:00', 20);

        $this->actingAs($owner, 'web')->post(route('app.calendar.availability.copy'), ['doctor_user_id' => $doctor->id, 'from_weekday' => 1, 'to_weekdays' => [2, 6]])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Monday copied to Tuesday, Saturday.');

        $this->assertSame([
            [1, '10:00', '13:00'], [1, '17:00', '20:00'],
            [2, '10:00', '13:00'], [2, '17:00', '20:00'],
            [6, '10:00', '13:00'], [6, '17:00', '20:00'],
        ], $this->week($doctor));
        $this->assertSame(20, DoctorAvailability::withoutTenancy()->where('weekday', 6)->where('start_time', 'like', '17:00%')->sole()->slot_minutes);
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'doctor_availability.copied')->exists());
    }

    public function test_copying_onto_a_day_with_hours_is_refused_unless_told_to_replace_them(): void
    {
        [$organization, $owner, $location, $doctor] = $this->clinic();
        $this->hours($organization, $location, $doctor, 1, '10:00', '13:00');
        $this->hours($organization, $location, $doctor, 2, '11:00', '12:00');
        $this->actingAs($owner, 'web');

        $this->from(route('app.calendar.index'))->post(route('app.calendar.availability.copy'), ['doctor_user_id' => $doctor->id, 'from_weekday' => 1, 'to_weekdays' => [2]])
            ->assertSessionHasErrors('to_weekdays');
        $this->assertSame([[1, '10:00', '13:00'], [2, '11:00', '12:00']], $this->week($doctor));

        $this->post(route('app.calendar.availability.copy'), ['doctor_user_id' => $doctor->id, 'from_weekday' => 1, 'to_weekdays' => [2], 'replace' => '1'])->assertSessionHasNoErrors();
        $this->assertSame([[1, '10:00', '13:00'], [2, '10:00', '13:00']], $this->week($doctor));
    }

    public function test_replacing_never_removes_the_doctors_hours_at_another_organization(): void
    {
        [$organization, $owner, $location, $doctor] = $this->clinic();
        $this->hours($organization, $location, $doctor, 1, '10:00', '13:00');
        [$elsewhere, , $elsewhereLocation] = $this->organizationWithOwner();
        $this->addMember($elsewhere, ['doctor'], $doctor, $elsewhereLocation);
        $this->hours($elsewhere, $elsewhereLocation, $doctor, 2, '11:00', '12:00');

        $this->actingAs($owner, 'web')->from(route('app.calendar.index'))
            ->post(route('app.calendar.availability.copy'), ['doctor_user_id' => $doctor->id, 'from_weekday' => 1, 'to_weekdays' => [2], 'replace' => '1'])
            ->assertSessionHasErrors('to_weekdays');

        $this->assertTrue(DoctorAvailability::withoutTenancy()->where('organization_id', $elsewhere->id)->where('weekday', 2)->exists());
        $this->assertFalse(DoctorAvailability::withoutTenancy()->where('organization_id', $organization->id)->where('weekday', 2)->exists());
    }

    public function test_copying_a_day_with_no_hours_says_so(): void
    {
        [, $owner, , $doctor] = $this->clinic();

        $this->actingAs($owner, 'web')->from(route('app.calendar.index'))
            ->post(route('app.calendar.availability.copy'), ['doctor_user_id' => $doctor->id, 'from_weekday' => 0, 'to_weekdays' => [1]])
            ->assertSessionHasErrors(['from_weekday' => 'This doctor has no hours here on Sunday to copy.']);
    }

    public function test_another_practices_hours_cannot_be_edited(): void
    {
        [$organization, , $location, $doctor] = $this->clinic();
        $row = $this->hours($organization, $location, $doctor, 1, '10:00', '13:00');
        [, $otherOwner] = $this->organizationWithOwner();

        $this->actingAs($otherOwner, 'web')->get(route('app.calendar.availability.edit', $row))->assertNotFound();
        $this->put(route('app.calendar.availability.update', $row), ['location_id' => $location->id, 'start_time' => '08:00', 'end_time' => '09:00', 'slot_minutes' => 5])->assertNotFound();

        $this->assertSame('10:00', substr($row->refresh()->start_time, 0, 5));
    }

    public function test_a_nurse_cannot_change_hours(): void
    {
        [$organization, , $location, $doctor] = $this->clinic();
        $row = $this->hours($organization, $location, $doctor, 1, '10:00', '13:00');
        $nurse = $this->addMember($organization, ['nurse'], location: $location);

        $this->actingAs($nurse, 'web')->post(route('app.calendar.availability.copy'), ['doctor_user_id' => $doctor->id, 'from_weekday' => 1, 'to_weekdays' => [2]])->assertForbidden();
        $this->put(route('app.calendar.availability.update', $row), ['location_id' => $location->id, 'start_time' => '08:00', 'end_time' => '09:00', 'slot_minutes' => 5])->assertForbidden();

        $this->assertSame([[1, '10:00', '13:00']], $this->week($doctor));
    }
}
