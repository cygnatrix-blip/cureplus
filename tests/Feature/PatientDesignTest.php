<?php

namespace Tests\Feature;

use App\Models\DoctorFee;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientDesignTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function design(array $overrides = []): array
    {
        return $overrides + [
            'brand_color' => '#1d4ed8',
            'accent_color' => '#f59e0b',
            'corners' => 'round',
            'tagline' => 'Care close to home',
            'about' => 'Serving Kolkata since 1998.',
            'highlights' => "NABH accredited\nOpen on Sundays\n\nFree parking",
            'contact_phone' => '033 1234 5678',
            'contact_email' => 'hello@clinic.test',
            'show_fees' => '1',
        ];
    }

    /**
     * @return array{Organization, User}
     */
    private function clinic(): array
    {
        [$organization, $owner] = $this->organizationWithOwner();

        return [$organization, $owner];
    }

    public function test_an_owner_customises_the_look_and_patients_see_it(): void
    {
        [$organization, $owner] = $this->clinic();

        $this->actingAs($owner, 'web')->get(route('app.settings.design'))->assertOk();
        $this->put(route('app.settings.design.update'), $this->design())->assertSessionHas('success');

        $fresh = $organization->fresh();
        $this->assertSame(['NABH accredited', 'Open on Sundays', 'Free parking'], $fresh->branding['highlights']);

        auth('web')->logout();
        $this->get('/'.$fresh->slug)->assertOk()
            ->assertSee('Care close to home')
            ->assertSee('Serving Kolkata since 1998.')
            ->assertSee('NABH accredited')
            ->assertSee('033 1234 5678')
            ->assertSee('--color-brand-600:#1d4ed8;', false)
            ->assertSee('--ui-radius:1.25rem;', false);
    }

    public function test_one_clinics_look_does_not_leak_to_another(): void
    {
        [$a, $ownerA] = $this->clinic();
        [$b] = $this->clinic();

        $this->actingAs($ownerA, 'web')->put(route('app.settings.design.update'), $this->design());
        auth('web')->logout();

        $this->get('/'.$b->slug)->assertOk()->assertDontSee('Care close to home')->assertDontSee('#1d4ed8', false);
        $this->assertNotNull($a);
    }

    public function test_the_dashboard_keeps_the_clinics_look_after_booking(): void
    {
        [$organization, $owner] = $this->clinic();
        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design());
        auth('web')->logout();

        $this->get('/'.$organization->slug)->assertOk();

        $this->actingAs(PatientAccount::factory()->create(), 'patient')
            ->get(route('patient.home'))->assertOk()
            ->assertSee($organization->name)
            ->assertSee('--color-brand-600:#1d4ed8;', false);
    }

    public function test_a_returning_patient_of_one_clinic_sees_its_look_after_signing_in_and_out(): void
    {
        [$organization, $owner] = $this->clinic();
        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design());
        auth('web')->logout();
        $this->flushSession();
        Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'back@example.test']);

        $this->post(route('patient.login.send'), ['email' => 'back@example.test']);
        $this->post(route('patient.login.confirm'), ['code' => $this->lastEmailedCode()])->assertRedirect(route('patient.home'));
        $this->get(route('patient.home'))->assertSee('--color-brand-600:#1d4ed8;', false);

        $this->post(route('patient.logout'));
        $this->get(route('patient.login'))->assertSee('--color-brand-600:#1d4ed8;', false);
    }

    public function test_a_patient_of_several_clinics_who_signs_in_directly_sees_the_neutral_look(): void
    {
        [$first, $owner] = $this->clinic();
        [$second] = $this->clinic();
        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design());
        auth('web')->logout();
        $this->flushSession();
        Patient::factory()->create(['organization_id' => $first->id, 'email' => 'both@example.test']);
        Patient::factory()->create(['organization_id' => $second->id, 'email' => 'both@example.test']);

        $this->post(route('patient.login.send'), ['email' => 'both@example.test']);
        $this->post(route('patient.login.confirm'), ['code' => $this->lastEmailedCode()]);

        $this->get(route('patient.home'))->assertOk()->assertDontSee('--color-brand-600:', false);
    }

    public function test_a_style_injection_is_refused(): void
    {
        [, $owner] = $this->clinic();

        $this->actingAs($owner, 'web')->from(route('app.settings.design'))
            ->put(route('app.settings.design.update'), $this->design(['brand_color' => 'red;}body{display:none']))
            ->assertSessionHasErrors(['brand_color' => 'Use a colour like #088575.']);
    }

    public function test_clinic_text_is_escaped_on_public_pages(): void
    {
        [$organization, $owner] = $this->clinic();

        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), $this->design(['tagline' => '<script>alert(1)</script>']));
        auth('web')->logout();

        $this->get('/'.$organization->slug)->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_cover_photo_is_re_encoded_served_and_removable(): void
    {
        Storage::fake('local');
        [$organization, $owner] = $this->clinic();
        $this->actingAs($owner, 'web');

        $this->put(route('app.settings.design.update'), $this->design(['cover' => UploadedFile::fake()->image('cover.png', 2400, 1200)]))->assertSessionHasNoErrors();

        $fresh = $organization->fresh();
        Storage::disk('local')->assertExists($fresh->cover_image_path);

        $image = $this->get(route('brand.cover', $fresh->slug))->assertOk()->assertHeader('Content-Type', function_exists('imagejpeg') ? 'image/jpeg' : 'image/png');
        $this->assertSame(1600, getimagesizefromstring($image->getContent())[0], 'Large photos are scaled down for weak networks.');

        $this->put(route('app.settings.design.update'), $this->design(['remove_cover' => '1']))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($fresh->cover_image_path);
        $this->get(route('brand.cover', $fresh->slug))->assertNotFound();
    }

    public function test_an_unreadable_image_gives_a_message_not_an_error_page(): void
    {
        Storage::fake('local');
        [, $owner] = $this->clinic();

        $this->actingAs($owner, 'web')->from(route('app.settings.design'))
            ->put(route('app.settings.design.update'), $this->design(['cover' => UploadedFile::fake()->createWithContent('cover.png', "\x89PNG\r\n\x1a\nnot really an image")]))
            ->assertRedirect(route('app.settings.design'));
    }

    public function test_only_people_who_manage_settings_can_change_the_design(): void
    {
        [$organization] = $this->organizationWithOwner();
        $location = Location::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail();
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web');
        $this->get(route('app.settings.design'))->assertForbidden();
        $this->put(route('app.settings.design.update'), $this->design())->assertForbidden();
    }

    public function test_fees_can_be_hidden_from_patients(): void
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $this->addMember($organization, ['doctor'], location: $location);
        DoctorFee::withoutTenancy()->create(['organization_id' => $organization->id, 'doctor_user_id' => null, 'visit_type' => 'new', 'amount_paise' => 75000]);
        $this->actingAs($owner, 'web');

        $this->put(route('app.settings.design.update'), $this->design());
        $this->assertTrue((bool) $organization->fresh()->branding['show_fees']);
        auth('web')->logout();
        $this->get('/'.$organization->slug)->assertOk()->assertSee('₹750.00');

        $this->actingAs($owner, 'web')->put(route('app.settings.design.update'), collect($this->design())->except('show_fees')->all());
        auth('web')->logout();
        $this->get('/'.$organization->slug)->assertOk()->assertDontSee('₹750.00');
    }
}
