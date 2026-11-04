<?php

namespace Tests;

use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;
use Symfony\Component\Mailer\SentMessage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = RbacSeeder::class;

    /**
     * @param  list<string>  $roles
     */
    protected function addMember(Organization $organization, array $roles, ?User $user = null, ?Location $location = null): User
    {
        $user ??= User::factory()->create();

        if (array_intersect($roles, config('cureplus.security.two_factor_roles')) !== []) {
            $user->forceFill(['two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()])->saveQuietly();
        }

        $membership = Membership::withoutTenancy()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $membership->roles()->sync(Role::query()->whereIn('key', $roles)->pluck('id'));

        if ($location) {
            $membership->locations()->sync([$location->id]);
        }

        return $user;
    }

    /**
     * An active organization with one location and an owner who has passed 2FA.
     *
     * @return array{Organization, User, Location}
     */
    protected function organizationWithOwner(): array
    {
        $organization = Organization::factory()->create();
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $owner = $this->addMember($organization, ['owner'], location: $location);

        return [$organization, $owner, $location];
    }

    /**
     * Reads the code out of the email that was really built and sent to the array mailer.
     */
    protected function lastEmailedCode(): string
    {
        foreach ($this->sentMessages()->reverse() as $message) {
            if (preg_match('/Your code is: (\d{6})/', (string) $message->getOriginalMessage()->getTextBody(), $match) === 1) {
                return $match[1];
            }
        }

        $this->fail('No code was emailed.');
    }

    protected function emailedCodeCount(): int
    {
        return $this->sentMessages()->filter(fn ($message) => str_contains((string) $message->getOriginalMessage()->getTextBody(), 'Your code is:'))->count();
    }

    /**
     * @return Collection<int, SentMessage>
     */
    private function sentMessages(): Collection
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }

    protected function superAdmin(string $role = 'super_admin'): PlatformAdmin
    {
        $admin = PlatformAdmin::factory()->create(['role' => $role]);
        $admin->forceFill(['two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()])->saveQuietly();

        return $admin;
    }
}
