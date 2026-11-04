<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\Membership;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicWebsiteTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function design(array $overrides = []): array
    {
        return $overrides + [
            'corners' => 'soft',
            'tagline' => 'Care close to home',
            'established_year' => (string) (now()->year - 12),
            'services' => [['title' => 'Child care', 'text' => 'Vaccines and check-ups'], ['title' => '', 'text' => 'ignored without a title']],
            'faqs' => [['question' => 'Do you take walk-ins?', 'answer' => 'Yes, until 6 pm.'], ['question' => 'No answer here', 'answer' => '']],
        ];
    }

    public function test_the_site_shows_the_practice_and_links_to_a_separate_booking_page(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $location->update(['address_line' => '12 Park Road', 'city' => 'Kolkata']);
        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design())->assertSessionHasNoErrors();
        auth('web')->logout();

        $this->get('/'.$organization->slug)->assertOk()
            ->assertSee('Care close to home')
            ->assertSee('Child care')
            ->assertDontSee('ignored without a title')
            ->assertSee('Do you take walk-ins?')
            ->assertDontSee('No answer here')
            ->assertSee('12+')
            ->assertSee('12 Park Road')
            ->assertSee('google.com/maps/search', false)
            ->assertSee(route('booking.show', $organization->slug), false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('noindex', false);

        $this->get(route('booking.show', $organization->slug))->assertOk()->assertSee('Book your appointment at', false)->assertSee('noindex', false);
    }

    public function test_empty_sections_hide_themselves(): void
    {
        [$organization] = $this->organizationWithOwner();

        $this->get('/'.$organization->slug)->assertOk()
            ->assertDontSee('id="services"', false)
            ->assertDontSee('id="questions"', false)
            ->assertDontSee('id="about"', false)
            ->assertDontSee('id="doctors"', false);
    }

    public function test_gallery_photos_are_re_encoded_served_and_removable(): void
    {
        Storage::fake('local');
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->put(route('app.settings.design.update'), $this->design(['gallery' => [UploadedFile::fake()->image('a.png', 2000, 1000), UploadedFile::fake()->image('b.png', 300, 300)]]))->assertSessionHasNoErrors();

        $gallery = $organization->fresh()->branding['gallery'];
        $this->assertCount(2, $gallery);
        $this->get(route('brand.gallery', [$organization->slug, 1]))->assertOk();
        $this->get(route('brand.gallery', [$organization->slug, 5]))->assertNotFound();
        $this->get('/'.$organization->slug)->assertSee(route('brand.gallery', [$organization->slug, 0]), false);

        $this->put(route('app.settings.design.update'), $this->design(['remove_gallery' => [0]]))->assertSessionHasNoErrors();

        $this->assertCount(1, $organization->fresh()->branding['gallery']);
        Storage::disk('local')->assertMissing($gallery[0]);
        Storage::disk('local')->assertExists($gallery[1]);
    }

    public function test_saving_the_design_keeps_existing_gallery_photos(): void
    {
        Storage::fake('local');
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design(['gallery' => [UploadedFile::fake()->image('a.png')]]));

        $this->put(route('app.settings.design.update'), $this->design(['tagline' => 'New words']))->assertSessionHasNoErrors();

        $this->assertCount(1, $organization->fresh()->branding['gallery']);
    }

    public function test_clinic_text_cannot_inject_script(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design(['tagline' => '</script><script>alert(1)</script>', 'services' => [['title' => '<img src=x onerror=alert(1)>', 'text' => '']]]));
        auth('web')->logout();

        $this->get('/'.$organization->slug)->assertOk()
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('<img src=x', false);
    }

    public function test_a_doctor_or_owner_can_add_a_photo_and_patients_see_it(): void
    {
        Storage::fake('local');
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $doctor->id)->firstOrFail();

        $this->actingAs($owner, 'web')->post(route('app.staff.photo', $membership), ['photo' => UploadedFile::fake()->image('dr.png', 900, 600)])->assertSessionHas('success');
        $first = DoctorProfile::query()->where('user_id', $doctor->id)->firstOrFail()->photo_path;
        Storage::disk('local')->assertExists($first);

        auth('web')->logout();
        $image = $this->get(route('brand.doctor', [$organization->slug, $doctor->id]))->assertOk();
        $this->assertSame(480, getimagesizefromstring($image->getContent())[0], 'Photos are cropped square.');
        $this->assertSame(480, getimagesizefromstring($image->getContent())[1]);
        $this->get('/'.$organization->slug)->assertSee(route('brand.doctor', [$organization->slug, $doctor->id]), false);

        $this->actingAs($doctor, 'web')->post(route('app.doctor-profile.photo'), ['photo' => UploadedFile::fake()->image('new.png', 500, 500)])->assertSessionHas('success');
        Storage::disk('local')->assertMissing($first);
    }

    public function test_a_doctor_photo_is_not_served_through_another_clinic(): void
    {
        Storage::fake('local');
        [$organization, $owner, $location] = $this->organizationWithOwner();
        [$other] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $doctor->id)->firstOrFail();
        $this->actingAs($owner, 'web')->post(route('app.staff.photo', $membership), ['photo' => UploadedFile::fake()->image('dr.png', 500, 500)]);

        $this->get(route('brand.doctor', [$other->slug, $doctor->id]))->assertNotFound();
    }

    public function test_an_owner_of_another_clinic_cannot_change_a_doctors_photo(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        [, $otherOwner] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $membership = Membership::withoutTenancy()->where('user_id', $doctor->id)->firstOrFail();

        $this->actingAs($otherOwner, 'web')->post(route('app.staff.photo', $membership), ['photo' => UploadedFile::fake()->image('x.png')])->assertNotFound();
    }
}
