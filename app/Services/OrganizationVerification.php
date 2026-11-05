<?php

namespace App\Services;

use App\Enums\OrganizationStatus;
use App\Exceptions\VerificationIncompleteException;
use App\Models\DoctorProfile;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\PlatformAdmin;
use App\Services\Billing\SubscriptionService;
use App\Services\Messaging\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The approval workflow: pending_verification, then approved or rejected, and a rejected
 * practice can fix what was wrong and resubmit. Patients only ever reach approved ones.
 */
class OrganizationVerification
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SubscriptionService $subscriptions,
        private readonly Notifier $notifier,
        private readonly DemoPatients $demoPatients,
    ) {}

    /**
     * What must be in place before a practice can be approved.
     *
     * @return list<array{key: string, label: string, done: bool}>
     */
    public function requirements(Organization $organization): array
    {
        $requirements = [[
            'key' => 'address',
            'label' => ($organization->isSolo() ? 'Chamber' : 'Clinic').' address',
            'done' => Location::withoutTenancy()->where('organization_id', $organization->id)->where('is_active', true)->whereNotNull('address_line')->whereNotNull('city')->exists(),
        ]];

        foreach ($this->owners($organization) as $owner) {
            if ($owner->hasRole('doctor')) {
                $profile = DoctorProfile::query()->where('user_id', $owner->user_id)->first();
                $requirements[] = [
                    'key' => 'registration',
                    'label' => 'Doctor registration number and medical council',
                    'done' => $profile !== null && filled($profile->registration_number) && filled($profile->registration_council),
                ];
            }
        }

        if (! $organization->isSolo()) {
            $requirements[] = [
                'key' => 'clinic_proof',
                'label' => 'Clinic proof document',
                'done' => OrganizationDocument::withoutTenancy()->where('organization_id', $organization->id)->where('type', 'clinic_proof')->exists(),
            ];
        }

        return $requirements;
    }

    /**
     * Stored privately under a generated name. The type comes from the file's contents.
     */
    public function storeDocument(Organization $organization, UploadedFile $file, string $type, int $uploadedBy): OrganizationDocument
    {
        $path = 'verification/'.$organization->id.'/'.Str::uuid().'.'.$file->guessExtension();

        Storage::disk(config('filesystems.default'))->put($path, (string) file_get_contents($file->getRealPath()));

        return OrganizationDocument::withoutTenancy()->create([
            'organization_id' => $organization->id,
            'type' => $type,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function isComplete(Organization $organization): bool
    {
        return collect($this->requirements($organization))->every(fn (array $requirement) => $requirement['done']);
    }

    /**
     * Also lifts a suspension. Restoring a practice approved before is not a new
     * approval, so it keeps its data and its trial, and needs no fresh documents.
     *
     * @throws VerificationIncompleteException
     */
    public function approve(Organization $organization, ?PlatformAdmin $admin, ?string $accessUntil = null): void
    {
        $isFirstApproval = $organization->approved_at === null;

        if ($isFirstApproval && ! $this->isComplete($organization)) {
            throw new VerificationIncompleteException('This practice has not sent everything needed yet.');
        }

        DB::transaction(function () use ($organization, $admin, $isFirstApproval, $accessUntil): void {
            if ($isFirstApproval) {
                $this->demoPatients->purge($organization->id);
                $this->markOwnerDoctorsVerified($organization, $admin);
            }

            $organization->forceFill([
                'status' => OrganizationStatus::Approved,
                'approved_at' => $organization->approved_at ?? now(),
                'reviewed_by' => $admin?->id,
                'rejection_reason' => null,
                'rejected_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'access_until' => $accessUntil ?? $organization->access_until,
            ])->save();

            if ($isFirstApproval) {
                // The trial runs from approval: time spent waiting for us is not the customer's.
                $this->subscriptions->startTrial($organization);
            }

            $this->audit->record('organizations.approved', $organization, ['first_approval' => $isFirstApproval, 'access_until' => $organization->access_until?->toDateString()]);
            $this->notifyOwners($organization, $isFirstApproval ? 'organization_approved' : 'organization_restored');
        });
    }

    /**
     * Moving the date is all it takes: an ended practice works again at once.
     */
    public function changeAccess(Organization $organization, ?string $accessUntil): void
    {
        $from = $organization->access_until?->toDateString();

        $organization->forceFill(['access_until' => $accessUntil])->save();

        $this->audit->record('organizations.access_changed', $organization, ['from' => $from, 'to' => $accessUntil]);
        $this->notifyOwners($organization, 'organization_access_changed', ['date' => $accessUntil ? Carbon::parse($accessUntil)->format('d M Y') : 'no end date']);
    }

    /**
     * Daily. One notice inside the last week and another on the final two days, so a practice
     * is never locked out mid-morning without warning. If cron misses a day, the next run
     * still sends it. The key includes the date, so a renewed practice is warned afresh.
     */
    public function sendAccessReminders(): int
    {
        $sent = 0;

        $ending = Organization::query()
            ->where('status', OrganizationStatus::Approved)
            ->whereDate('access_until', '>=', today())
            ->whereDate('access_until', '<=', today()->addDays(7))
            ->get();

        foreach ($ending as $organization) {
            $reminder = today()->diffInDays($organization->access_until) <= 1 ? 'final' : 'week';

            foreach ($this->owners($organization) as $owner) {
                if ($owner->user !== null) {
                    $this->notifier->notifyAccountOwner(
                        $owner->user,
                        'organization_access_ending',
                        ['clinic' => $organization->name, 'date' => $organization->access_until->format('d M Y')],
                        "access-ending:{$organization->id}:{$organization->access_until->toDateString()}:{$reminder}:{$owner->user_id}",
                    );
                    $sent++;
                }
            }
        }

        return $sent;
    }

    public function reject(Organization $organization, PlatformAdmin $admin, string $reason): void
    {
        DB::transaction(function () use ($organization, $admin, $reason): void {
            $organization->forceFill([
                'status' => OrganizationStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'reviewed_by' => $admin->id,
            ])->save();

            $this->audit->record('organizations.rejected', $organization, ['reason' => $reason]);
            $this->notifyOwners($organization, 'organization_rejected', ['reason' => $reason]);
        });
    }

    /**
     * @throws VerificationIncompleteException
     */
    public function resubmit(Organization $organization): void
    {
        if (! $this->isComplete($organization)) {
            throw new VerificationIncompleteException('Please complete every item on the list before sending it again.');
        }

        $organization->forceFill(['status' => OrganizationStatus::PendingVerification, 'resubmitted_at' => now()])->save();

        $this->audit->record('organizations.resubmitted', $organization);
        $this->notifier->notifyPlatformAdmins('organization_resubmitted', ['clinic' => $organization->name]);
    }

    public function announceSignup(Organization $organization): void
    {
        $this->notifier->notifyPlatformAdmins('organization_signup', ['clinic' => $organization->name, 'type' => $organization->type->label()]);
    }

    /**
     * @return Collection<int, Membership>
     */
    private function owners(Organization $organization): Collection
    {
        return Membership::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('key', 'owner'))
            ->with(['roles', 'user'])
            ->get()
            ->toBase();
    }

    /**
     * Reviewing the owner's registration is what approval is, so the badge follows from it.
     */
    private function markOwnerDoctorsVerified(Organization $organization, ?PlatformAdmin $admin): void
    {
        foreach ($this->owners($organization) as $owner) {
            if ($owner->hasRole('doctor')) {
                DoctorProfile::query()->where('user_id', $owner->user_id)->whereNotNull('registration_number')->get()
                    ->each(fn (DoctorProfile $profile) => $profile->forceFill(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $admin?->id])->save());
            }
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyOwners(Organization $organization, string $event, array $variables = []): void
    {
        foreach ($this->owners($organization) as $owner) {
            if ($owner->user !== null) {
                $this->notifier->notifyAccountOwner($owner->user, $event, $variables + ['clinic' => $organization->name]);
            }
        }
    }
}
