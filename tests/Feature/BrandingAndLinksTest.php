<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingAndLinksTest extends TestCase
{
    public function test_a_logo_becomes_the_installed_apps_icons(): void
    {
        Storage::fake('local');
        [$organization, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->put(route('app.settings.organization.update'), ['name' => 'New Name'])->assertSessionHasNoErrors();
        $this->put(route('app.settings.design.update'), ['corners' => 'soft', 'brand_color' => '#088575', 'logo' => UploadedFile::fake()->image('logo.png', 800, 400)])->assertSessionHasNoErrors();

        $fresh = $organization->fresh();
        $this->assertSame('New Name', $fresh->name);
        Storage::disk('local')->assertExists($fresh->icon_512_path);

        $icon = $this->get(route('brand.icon', [$fresh->slug, 512]))->assertOk()->assertHeader('Content-Type', 'image/png');
        [$width, $height] = getimagesizefromstring($icon->getContent());
        $this->assertSame([512, 512], [$width, $height]);

        $this->get(route('brand.manifest', $fresh->slug))->assertJsonPath('icons.1.sizes', '512x512');
        $this->get(route('brand.icon', [$fresh->slug, 999]))->assertNotFound();
    }

    public function test_a_style_block_value_and_a_bad_file_are_refused(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->from(route('app.settings.design'))->put(route('app.settings.design.update'), ['corners' => 'soft', 'logo' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('logo');
        $this->from(route('app.settings.organization'))->put(route('app.settings.organization.update'), ['name' => 'x', 'gstin' => 'BAD'])
            ->assertSessionHasErrors('gstin');
    }

    public function test_links_page_gives_qr_codes_and_a_poster_for_each_doctor(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $doctor->id)->firstOrFail();
        $membership->forceFill(['doctor_slug' => 'dr-who'])->saveQuietly();

        $this->actingAs($owner, 'web');

        $this->get(route('app.booking-links.index'))->assertOk()->assertSee('/'.$organization->slug.'/dr-who')->assertSee('<svg', false);

        $this->get(route('app.booking-links.download', ['link' => 'doctor-'.$membership->id]))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get(route('app.booking-links.poster', ['link' => 'org']))->assertOk()->assertSee('Scan with your phone camera');
        $this->get(route('app.booking-links.download', ['link' => 'doctor-999999']))->assertNotFound();
    }

    public function test_a_solo_practice_has_one_link(): void
    {
        $organization = Organization::factory()->create(['type' => 'solo_practice', 'slug' => 'dr-roy']);
        $owner = $this->addMember($organization, ['owner', 'doctor']);

        $links = $this->actingAs($owner, 'web')->get(route('app.booking-links.index'))->assertOk()->viewData('links');

        $this->assertCount(1, $links);
        $this->assertStringEndsWith('/dr-roy', $links->first()['url']);
    }

    public function test_a_receptionist_cannot_change_branding(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();

        $this->actingAs($this->addMember($organization, ['receptionist'], location: $location), 'web')
            ->get(route('app.settings.organization'))
            ->assertForbidden();
    }
}
