<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    /**
     * @return array{string, Invitation}
     */
    private function invite(array $overrides = []): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $token = Str::random(64);

        return [$token, Invitation::withoutTenancy()->create($overrides + [
            'organization_id' => $organization->id,
            'email' => 'new@example.test',
            'token_hash' => Invitation::hashToken($token),
            'role_keys' => ['receptionist'],
            'location_ids' => [$location->id],
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ])];
    }

    public function test_an_owner_can_invite_by_email(): void
    {
        Notification::fake();
        [, $owner, $location] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->post(route('app.invitations.store'), [
            'email' => 'New@Example.test', 'role_keys' => ['nurse'], 'location_ids' => [$location->id],
        ])->assertSessionHas('success');

        $invitation = Invitation::withoutTenancy()->firstOrFail();
        $this->assertSame('new@example.test', $invitation->email);
        Notification::assertSentOnDemand(InvitationNotification::class, fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'new@example.test');
    }

    public function test_the_token_is_only_stored_hashed(): void
    {
        [$token, $invitation] = $this->invite();

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
    }

    public function test_a_new_person_accepts_and_gets_exactly_what_was_offered(): void
    {
        [$token, $invitation] = $this->invite();

        $this->get(route('invitations.show', $token))->assertOk();
        $this->post(route('invitations.accept', $token), ['name' => 'Mita Das', 'password' => 'Password123', 'password_confirmation' => 'Password123'])
            ->assertRedirect(route('app.dashboard'));

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user, 'web');

        $membership = Membership::withoutTenancy()->where('user_id', $user->id)->with('roles', 'locations')->firstOrFail();
        $this->assertSame((int) $invitation->organization_id, (int) $membership->organization_id);
        $this->assertSame(['receptionist'], $membership->roles->pluck('key')->all());
        $this->assertSame($invitation->location_ids, $membership->locations->pluck('id')->all());
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_an_invitation_works_only_once(): void
    {
        [$token] = $this->invite();
        $data = ['name' => 'Mita', 'password' => 'Password123', 'password_confirmation' => 'Password123'];

        $this->post(route('invitations.accept', $token), $data);
        $this->post(route('app.logout'));

        $this->get(route('invitations.show', $token))->assertNotFound();
        $this->post(route('invitations.accept', $token), $data)->assertNotFound();
    }

    public function test_expired_revoked_and_made_up_tokens_are_404(): void
    {
        [$expired] = $this->invite(['expires_at' => now()->subMinute()]);
        [$revoked, $invitation] = $this->invite(['email' => 'b@example.test']);
        $invitation->forceFill(['revoked_at' => now()])->saveQuietly();

        $this->get(route('invitations.show', $expired))->assertNotFound();
        $this->get(route('invitations.show', $revoked))->assertNotFound();
        $this->get(route('invitations.show', Str::random(64)))->assertNotFound();
    }

    public function test_an_existing_person_must_be_signed_in_as_themselves(): void
    {
        $existing = User::factory()->create(['email' => 'new@example.test']);
        $stranger = User::factory()->create();
        [$token] = $this->invite();

        $this->get(route('invitations.show', $token))->assertRedirect(route('login'));
        $this->post(route('invitations.accept', $token))->assertForbidden();

        $this->actingAs($stranger, 'web')->post(route('invitations.accept', $token))->assertForbidden();
        $this->assertSame(0, Membership::withoutTenancy()->where('user_id', $stranger->id)->count());

        $this->actingAs($existing, 'web')->post(route('invitations.accept', $token))->assertRedirect(route('app.dashboard'));
        $this->assertSame(1, Membership::withoutTenancy()->where('user_id', $existing->id)->count());
    }

    public function test_a_doctor_invite_gets_a_slug_and_a_visiting_doctor_keeps_one_login(): void
    {
        [$soloOrg] = $this->organizationWithOwner();
        $doctor = User::factory()->create(['name' => 'Rohan Roy', 'email' => 'new@example.test']);
        $this->addMember($soloOrg, ['doctor'], $doctor);
        [$token] = $this->invite(['role_keys' => ['doctor']]);

        $this->actingAs($doctor, 'web')->post(route('invitations.accept', $token));

        $this->assertSame(2, Membership::withoutTenancy()->where('user_id', $doctor->id)->count());
        $this->assertSame(1, User::query()->where('email', 'new@example.test')->count());
        $this->assertSame('dr-rohan-roy', Membership::withoutTenancy()->where('user_id', $doctor->id)->latest('id')->first()->doctor_slug);
    }

    public function test_an_owner_can_cancel_an_invitation_and_cannot_invite_current_staff(): void
    {
        [, $owner, $location] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->post(route('app.invitations.store'), ['email' => $owner->email, 'role_keys' => ['nurse'], 'location_ids' => [$location->id]])
            ->assertSessionHasErrors('email');

        Notification::fake();
        $this->post(route('app.invitations.store'), ['email' => 'x@example.test', 'role_keys' => ['nurse'], 'location_ids' => [$location->id]]);
        $invitation = Invitation::withoutTenancy()->firstOrFail();

        $this->delete(route('app.invitations.destroy', $invitation));
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }
}
