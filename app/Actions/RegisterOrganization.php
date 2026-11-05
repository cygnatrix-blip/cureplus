<?php

namespace App\Actions;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\DoctorProfile;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\OrganizationVerification;
use App\Support\Mobile;
use App\Support\Specialties;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RegisterOrganization
{
    public function __construct(private readonly GenerateOrganizationSlug $slugs, private readonly SubscriptionService $subscriptions, private readonly OrganizationVerification $verification) {}

    /**
     * Doctor: solo practice, Owner + Doctor roles, first chamber.
     * Clinic: clinic organization, Owner role (plus Doctor if they see patients), first branch.
     *
     * @param  array{type: string, organization_name?: string|null, name: string, email: string, mobile: string, password: string, address_line: string, city: string, pincode?: string|null, owner_is_doctor?: bool|string, registration_number?: string|null, registration_council?: string|null, qualification?: string|null, specialty?: string|null, document?: UploadedFile|null, certificate?: UploadedFile|null}  $data
     */
    public function __invoke(array $data): User
    {
        $type = OrganizationType::from($data['type']);
        $isSolo = $type === OrganizationType::SoloPractice;
        $isDoctor = $isSolo || ! empty($data['owner_is_doctor']);
        $organizationName = $isSolo ? $data['name'] : (string) $data['organization_name'];

        return DB::transaction(function () use ($data, $type, $isSolo, $isDoctor, $organizationName): User {
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
                'status' => $requiresApproval ? OrganizationStatus::PendingVerification : OrganizationStatus::Approved,
                'approved_at' => $requiresApproval ? null : now(),
            ])->save();

            $location = Location::create([
                'organization_id' => $organization->id,
                'name' => $isSolo ? 'Main chamber' : 'Main branch',
                'address_line' => $data['address_line'],
                'city' => $data['city'],
                'pincode' => $data['pincode'] ?? null,
            ]);

            $membership = Membership::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'status' => 'active',
                'doctor_slug' => $isSolo ? $slug : ($isDoctor ? Membership::uniqueDoctorSlug($organization->id, $data['name']) : null),
                'joined_at' => now(),
            ]);

            $membership->roles()->sync(
                Role::query()->whereIn('key', $isDoctor ? ['owner', 'doctor'] : ['owner'])->pluck('id')
            );
            $membership->locations()->sync([$location->id]);

            if ($isDoctor) {
                DoctorProfile::query()->create([
                    'user_id' => $user->id,
                    'qualification' => $data['qualification'] ?? null,
                    'specialty' => $data['specialty'] ?? null,
                    'registration_number' => $data['registration_number'] ?? null,
                    'registration_council' => $data['registration_council'] ?? null,
                ]);
            }

            if (! empty($data['document'])) {
                $this->verification->storeDocument($organization, $data['document'], $isSolo ? 'doctor_registration' : 'clinic_proof', $user->id);
            }

            if ($isDoctor && Specialties::isAllied($data['specialty'] ?? null) && ! empty($data['certificate'])) {
                $this->verification->storeDocument($organization, $data['certificate'], OrganizationDocument::PROFESSIONAL_CERTIFICATE, $user->id);
            }

            // With approval on, the trial waits for the approval date.
            if ($requiresApproval) {
                $this->verification->announceSignup($organization);
            } else {
                $this->subscriptions->startTrial($organization);
            }

            return $user;
        });
    }
}
