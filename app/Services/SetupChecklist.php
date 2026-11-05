<?php

namespace App\Services;

use App\Models\DoctorAvailability;
use App\Models\DoctorFee;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;

/**
 * The first things a new practice needs to do, shown on its dashboard until they are done.
 */
class SetupChecklist
{
    /**
     * @return list<array{label: string, hint: string, url: string, done: bool}>
     */
    public function for(Organization $organization): array
    {
        $place = $organization->isSolo() ? 'chamber' : 'branch';

        return [
            ['label' => 'Get verified', 'hint' => 'Send your documents so patients can book with you.', 'url' => route('app.verification.index'), 'done' => $organization->isActive()],
            ['label' => "Add your {$place} address", 'hint' => 'Patients see this when they book.', 'url' => route('app.locations.index'), 'done' => Location::query()->active()->whereNotNull('address_line')->exists()],
            ['label' => 'Add a doctor', 'hint' => $organization->isSolo() ? 'You are already set up as the doctor. Add colleagues here if you have any.' : 'Invite each doctor by email.', 'url' => route('app.staff.index', ['invite' => 'doctor']).'#invite', 'done' => Membership::query()->active()->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))->exists()],
            ['label' => 'Set working hours', 'hint' => 'Times patients can book come from these hours.', 'url' => route('app.calendar.index'), 'done' => DoctorAvailability::query()->exists()],
            ['label' => 'Set consultation fees', 'hint' => 'Used on bills and shown on your booking page.', 'url' => route('app.billing-settings.index'), 'done' => DoctorFee::query()->exists()],
            ['label' => 'Connect your email', 'hint' => 'Patient reminders, prescriptions and bills are sent from your own address.', 'url' => route('app.integrations.index'), 'done' => app(OrganizationMail::class)->hasOwnEmail($organization->id)],
            ['label' => 'Build your website', 'hint' => 'Add your logo, photos, services and colours.', 'url' => route('app.settings.design'), 'done' => $organization->icon_192_path !== null],
        ];
    }
}
