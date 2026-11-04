<?php

namespace Tests\Feature;

use App\Actions\GenerateOrganizationSlug;
use App\Contracts\CaptchaVerifier;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\Captcha\TurnstileCaptcha;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'solo_practice',
            'name' => 'Rohan Roy',
            'email' => 'Rohan@Example.test',
            'mobile' => '98123 45678',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
        ];
    }

    public function test_a_doctor_gets_a_solo_practice_with_owner_and_doctor_roles(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload())->assertRedirect();

        $user = User::query()->where('email', 'rohan@example.test')->firstOrFail();
        $this->assertSame('+919812345678', $user->mobile);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertAuthenticatedAs($user, 'web');
        Notification::assertSentTo($user, QueuedVerifyEmail::class);

        $membership = Membership::withoutTenancy()->where('user_id', $user->id)->with('roles', 'organization')->firstOrFail();
        $this->assertSame(OrganizationType::SoloPractice, $membership->organization->type);
        $this->assertSame('rohan-roy', $membership->organization->slug);
        $this->assertSame('rohan-roy', $membership->doctor_slug);
        $this->assertEqualsCanonicalizing(['owner', 'doctor'], $membership->roles->pluck('key')->all());
        $this->assertSame('Main chamber', Location::withoutTenancy()->where('organization_id', $membership->organization_id)->firstOrFail()->name);
    }

    public function test_a_clinic_gets_an_owner_only_membership_and_a_branch(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload(['type' => 'clinic', 'organization_name' => 'City Clinic']));

        $membership = Membership::withoutTenancy()->with('roles', 'organization')->firstOrFail();
        $this->assertSame(OrganizationType::Clinic, $membership->organization->type);
        $this->assertSame('City Clinic', $membership->organization->name);
        $this->assertSame(['owner'], $membership->roles->pluck('key')->all());
        $this->assertSame('Main branch', Location::withoutTenancy()->firstOrFail()->name);
    }

    public function test_a_clinic_must_name_itself(): void
    {
        $this->post('/register', $this->payload(['type' => 'clinic']))->assertSessionHasErrors('organization_name');
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_new_organizations_wait_for_approval_unless_configured_otherwise(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload());
        $this->assertSame(OrganizationStatus::PendingVerification, Organization::query()->firstOrFail()->status);

        config(['cureplus.organizations.require_approval' => false]);
        $this->post(route('app.logout'));
        $this->post('/register', $this->payload(['email' => 'two@example.test', 'mobile' => '9812345679']));

        $this->assertSame(OrganizationStatus::Approved, Organization::query()->latest('id')->firstOrFail()->status);
    }

    public function test_one_mobile_or_email_is_one_person_whatever_the_format(): void
    {
        User::factory()->create(['email' => 'rohan@example.test', 'mobile' => '+919812345678']);

        $this->post('/register', $this->payload(['mobile' => '9999999999']))->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['email' => 'new@example.test', 'mobile' => '+91-9812345678']))->assertSessionHasErrors('mobile');
    }

    public function test_input_is_validated(): void
    {
        $this->post('/register', $this->payload(['mobile' => '12345']))->assertSessionHasErrors('mobile');
        $this->post('/register', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['terms' => '']))->assertSessionHasErrors('terms');
        $this->post('/register', $this->payload(['type' => 'hospital']))->assertSessionHasErrors('type');
    }

    public function test_slugs_avoid_reserved_words_and_existing_organizations(): void
    {
        $slugs = app(GenerateOrganizationSlug::class);

        $this->assertSame('admin-2', $slugs('Admin'));
        $this->assertSame('app-2', $slugs('App'));
        $this->assertSame('clinic', $slugs('!!!'));

        $deleted = Organization::factory()->create(['slug' => 'city-clinic']);
        $deleted->delete();
        // A retired link stays retired, so an old QR code cannot reach a new owner.
        $this->assertSame('city-clinic-2', $slugs('City Clinic'));
    }

    public function test_every_first_level_route_is_a_reserved_slug(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0];
            $first = preg_replace('/\.[a-z]+$/', '', $first);

            if ($first === '' || str_starts_with($first, '{') || in_array($first, ['storage', '_ignition', '_boost', 'mcp'], true)) {
                continue;
            }

            $this->assertContains($first, config('cureplus.reserved_slugs'), "/{$first} must be in cureplus.reserved_slugs or an organization could claim it.");
        }
    }

    public function test_captcha_blocks_the_form_when_enabled(): void
    {
        $this->app->instance(CaptchaVerifier::class, new TurnstileCaptcha);

        $this->post('/register', $this->payload())->assertSessionHasErrors('cf-turnstile-response');
        $this->post('/login', ['identifier' => 'a@example.test', 'password' => 'x'])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_failed_registration_leaves_nothing_behind(): void
    {
        Notification::fake();
        config(['cureplus.reserved_slugs' => null]); // Forces the slug step, mid-transaction, to fail.

        try {
            $this->withoutExceptionHandling()->post('/register', $this->payload());
        } catch (\Throwable) {
            // Expected.
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Organization::query()->count());
    }
}
