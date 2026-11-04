<?php

namespace App\Services;

use App\Enums\OrganizationStatus;
use App\Models\DoctorProfile;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Services\Messaging\Notifier;
use Illuminate\Support\Collection;

/**
 * Patients can only book a doctor whose registration our team has checked. The check belongs
 * to the doctor, not the clinic: someone verified once does not wait again at a second clinic.
 */
class DoctorVerification
{
    public function __construct(private readonly AuditLogger $audit, private readonly Notifier $notifier) {}

    /**
     * Doctors at approved practices who have sent their details and are waiting. Doctors at a
     * practice still under review wait for that practice first.
     *
     * @return Collection<int, array{profile: DoctorProfile, clinics: string}>
     */
    public function waiting(): Collection
    {
        $memberships = Membership::withoutTenancy()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
            ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::Approved))
            ->with('organization:id,name')
            ->get()
            ->groupBy('user_id');

        return DoctorProfile::query()
            ->where('verification_status', 'unverified')
            ->whereNotNull('registration_number')
            ->whereNotNull('registration_council')
            ->whereIn('user_id', $memberships->keys())
            ->with('user:id,name,email,mobile')
            ->oldest('updated_at')
            ->get()
            ->map(fn (DoctorProfile $profile) => [
                'profile' => $profile,
                'clinics' => $memberships->get($profile->user_id)->map(fn (Membership $membership) => $membership->organization?->name)->filter()->join(', '),
            ])
            ->toBase();
    }

    public function registrationSaved(DoctorProfile $profile, bool $changed): void
    {
        if (blank($profile->registration_number) || blank($profile->registration_council)) {
            return;
        }

        // Saving after a rejection is the doctor saying it is fixed, even if the number is the same.
        if ($profile->verification_status === 'rejected') {
            $profile->forceFill(['verification_status' => 'unverified', 'verification_note' => null])->save();
            $changed = true;
        }

        if ($changed && $profile->verification_status === 'unverified' && $this->worksAtAnApprovedPractice($profile)) {
            $this->notifier->notifyPlatformAdmins('doctor_verification_requested', ['doctor' => (string) $profile->user?->name]);
        }
    }

    public function approve(DoctorProfile $profile, PlatformAdmin $admin): void
    {
        $profile->forceFill(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $admin->id, 'verification_note' => null])->save();
        $this->audit->record('doctor_profiles.verified', $profile);

        if ($profile->user !== null) {
            $this->notifier->notifyAccountOwner($profile->user, 'doctor_verified', []);
        }
    }

    public function reject(DoctorProfile $profile, PlatformAdmin $admin, string $reason): void
    {
        $profile->forceFill(['verification_status' => 'rejected', 'verified_at' => null, 'verified_by' => $admin->id, 'verification_note' => $reason])->save();
        $this->audit->record('doctor_profiles.rejected', $profile, ['reason' => $reason]);

        if ($profile->user !== null) {
            $this->notifier->notifyAccountOwner($profile->user, 'doctor_rejected', ['reason' => $reason]);
        }
    }

    private function worksAtAnApprovedPractice(DoctorProfile $profile): bool
    {
        return Membership::withoutTenancy()
            ->where('user_id', $profile->user_id)
            ->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::Approved))
            ->exists();
    }
}
