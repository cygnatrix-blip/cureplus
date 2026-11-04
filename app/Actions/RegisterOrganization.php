<?php

namespace App\Actions;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Support\Mobile;
use Illuminate\Support\Facades\DB;

class RegisterOrganization
{
    public function __construct(private readonly GenerateOrganizationSlug $slugs, private readonly SubscriptionService $subscriptions) {}

    /**
     * Doctor: solo practice, Owner + Doctor roles, first chamber.
     * Clinic: clinic organization, Owner role, first branch.
     *
     * @param  array{type: string, organization_name?: string|null, name: string, email: string, mobile: string, password: string}  $data
     */
    public function __invoke(array $data): User
    {
        $type = OrganizationType::from($data['type']);
        $isSolo = $type === OrganizationType::SoloPractice;
        $organizationName = $isSolo ? $data['name'] : (string) $data['organization_name'];

        return DB::transaction(function () use ($data, $type, $isSolo, $organizationName): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'mobile' => Mobile::normalize($data['mobile']),
                'password' => $data['password'],
                'terms_accepted_at' => now(),
            ]);

            $slug = ($this->slugs)($organizationName);
            $requiresApproval = config('cureplus.organizations.require_approval');

            $organization = new Organization([
                'type' => $type,
                'name' => $organizationName,
                'slug' => $slug,
            ]);
            $organization->forceFill([
                'status' => $requiresApproval ? OrganizationStatus::Pending : OrganizationStatus::Active,
                'approved_at' => $requiresApproval ? null : now(),
            ])->save();

            $location = Location::create([
                'organization_id' => $organization->id,
                'name' => $isSolo ? 'Main chamber' : 'Main branch',
            ]);

            $membership = Membership::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'status' => 'active',
                'doctor_slug' => $isSolo ? $slug : null,
                'joined_at' => now(),
            ]);

            $membership->roles()->sync(
                Role::query()->whereIn('key', $isSolo ? ['owner', 'doctor'] : ['owner'])->pluck('id')
            );
            $membership->locations()->sync([$location->id]);
            $this->subscriptions->startTrial($organization);

            return $user;
        });
    }
}
