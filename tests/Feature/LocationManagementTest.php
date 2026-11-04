<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Location;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    public function test_an_owner_manages_locations_and_every_change_is_audited(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->post(route('app.locations.store'), ['name' => 'Salt Lake', 'city' => 'Kolkata', 'pincode' => '700091', 'is_active' => '1'])
            ->assertRedirect(route('app.locations.index'));

        $location = Location::withoutTenancy()->where('name', 'Salt Lake')->firstOrFail();
        $this->assertSame($organization->id, (int) $location->organization_id);

        $this->put(route('app.locations.update', $location), ['name' => 'Salt Lake Sector V', 'is_active' => '1'])->assertRedirect();
        $this->assertSame('Salt Lake Sector V', $location->fresh()->name);

        $this->delete(route('app.locations.destroy', $location))->assertRedirect();
        $this->assertSoftDeleted($location);

        $actions = AuditLog::withoutTenancy()->where('subject_type', 'Location')->where('subject_id', $location->id)->pluck('action')->all();
        $this->assertSame(['locations.created', 'locations.updated', 'locations.deleted'], $actions);
    }

    public function test_the_last_active_location_cannot_be_removed(): void
    {
        [, $owner, $location] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->from(route('app.locations.index'))->delete(route('app.locations.destroy', $location))->assertSessionHas('error');

        $this->assertNotSoftDeleted($location);
    }

    public function test_organization_id_in_the_form_is_ignored(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        [$other] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.locations.store'), ['name' => 'Mine', 'organization_id' => $other->id]);

        $this->assertSame($organization->id, (int) Location::withoutTenancy()->where('name', 'Mine')->firstOrFail()->organization_id);
    }

    public function test_input_is_validated(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.locations.store'), ['name' => '', 'pincode' => '12'])->assertSessionHasErrors(['name', 'pincode']);
    }
}
