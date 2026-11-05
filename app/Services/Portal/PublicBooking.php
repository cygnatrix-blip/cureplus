<?php

namespace App\Services\Portal;

use App\Enums\VisitType;
use App\Http\Middleware\ResolveHost;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\FeatureGate;
use App\Services\Billing\FeeResolver;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * What the public may learn about an organization: only what a booking needs.
 */
class PublicBooking
{
    public function __construct(private readonly FeatureGate $features, private readonly FeeResolver $fees) {}

    public function organization(string $slug): Organization
    {
        $organization = Organization::query()->where('slug', $slug)->firstOrFail();
        $custom = request()->attributes->get(ResolveHost::ORGANIZATION_ATTRIBUTE);

        // A custom domain only ever serves its own organization.
        abort_if($custom !== null && $custom->id !== $organization->id, 404);

        // A pending or suspended organization looks exactly like one that does not exist.
        abort_unless($organization->isActive() && $this->features->enabled($organization->id, 'online_booking'), 404);

        return $organization;
    }

    /**
     * @return Collection<int, Location>
     */
    public function locations(Organization $organization): Collection
    {
        return Location::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where('accepts_online_booking', true)
            ->orderBy('name')
            ->get()
            ->toBase();
    }

    /**
     * @return Collection<int, Membership>
     */
    public function doctors(Organization $organization, ?int $locationId = null): Collection
    {
        return Membership::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
            ->when(config('cureplus.organizations.doctors_need_approval'), fn ($query) => $query->whereHas('user.doctorProfile', fn ($query) => $query->where('verification_status', 'verified')))
            ->when($locationId, fn ($query) => $query->whereHas('locations', fn ($query) => $query->where('locations.id', $locationId)))
            ->with(['user:id,name', 'user.doctorProfile:id,user_id,qualification,specialty,offers_video,video_link,verification_status,photo_path'])
            ->get()
            ->toBase();
    }

    /**
     * What a patient sees about each doctor. Fees show only if the clinic chose to show them.
     *
     * @return Collection<int, array{id: int, name: string, initials: string, slug: string|null, specialty: string|null, qualification: string|null, verified: bool, offers_video: bool, fee: string|null, photo: string|null}>
     */
    public function doctorCards(Organization $organization, ?int $locationId = null): Collection
    {
        $showFees = (bool) ($organization->branding['show_fees'] ?? true);

        return $this->doctors($organization, $locationId)->map(function (Membership $doctor) use ($organization, $showFees): array {
            $name = (string) $doctor->user?->name;
            $words = array_values(array_filter(explode(' ', trim((string) preg_replace('/^dr\.?\s+/i', '', $name)))));
            $profile = $doctor->user?->doctorProfile;
            $fee = $showFees ? $this->fees->resolve($organization, (int) $doctor->user_id, VisitType::New, null)['amount_paise'] : 0;

            return [
                'id' => (int) $doctor->user_id,
                'name' => $name,
                'initials' => mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1)),
                'slug' => $doctor->doctor_slug,
                'specialty' => $profile?->specialty,
                'qualification' => $profile?->qualification,
                'verified' => $profile?->verification_status === 'verified',
                'offers_video' => $profile?->offers_video && filled($profile->video_link),
                'fee' => $fee > 0 ? Money::format($fee) : null,
                'photo' => $profile?->photo_path ? route('brand.doctor', [$organization->slug, (int) $doctor->user_id]) : null,
            ];
        })->values();
    }

    public function doctorBySlug(Organization $organization, string $slug): Membership
    {
        return Membership::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->where('doctor_slug', $slug)
            ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
            ->when(config('cureplus.organizations.doctors_need_approval'), fn ($query) => $query->whereHas('user.doctorProfile', fn ($query) => $query->where('verification_status', 'verified')))
            ->with('user:id,name')
            ->firstOrFail();
    }

    public function isBookableDoctor(Organization $organization, int $doctorId, int $locationId): bool
    {
        return $this->doctors($organization, $locationId)->contains('user_id', $doctorId)
            && $this->locations($organization)->contains('id', $locationId);
    }

    public function doctorUser(int $id): ?User
    {
        return User::query()->find($id);
    }
}
