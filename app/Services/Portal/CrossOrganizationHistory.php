<?php

namespace App\Services\Portal;

use App\Models\Consent;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use App\Models\Visit;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Lets a doctor see what other organizations recorded, only with the patient's
 * consent (proved by OTP). Every look is logged at both ends: where the doctor
 * works, and at the organization whose record was read.
 */
class CrossOrganizationHistory
{
    public function __construct(private readonly AuditLogger $audit, private readonly PatientRecords $records) {}

    public function accountFor(Patient $patient): PatientAccount
    {
        $account = PatientAccount::query()->firstOrCreate(['email' => $patient->email], ['name' => $patient->name, 'mobile' => $patient->mobile]);
        $this->records->link($account);

        return $account;
    }

    public function grant(Patient $patient, User $doctor, Organization $organization): Consent
    {
        $consent = Consent::query()->create([
            'patient_account_id' => $this->accountFor($patient)->id,
            'purpose' => Consent::CROSS_ORG_HISTORY,
            'grantee_organization_id' => $organization->id,
            'grantee_doctor_user_id' => $doctor->id,
            'granted_via' => 'otp',
            'granted_at' => now(),
            'expires_at' => now()->addDays((int) config('cureplus.consent_days')),
        ]);

        $this->audit->record('consents.granted', $patient, ['consent_id' => $consent->id]);

        return $consent;
    }

    public function hasConsent(Patient $patient, User $doctor, Organization $organization): bool
    {
        if ($patient->email === null) {
            return false;
        }

        return Consent::query()
            ->active()
            ->where('purpose', Consent::CROSS_ORG_HISTORY)
            ->where('grantee_organization_id', $organization->id)
            ->where('grantee_doctor_user_id', $doctor->id)
            ->whereHas('account', fn ($query) => $query->where('email', $patient->email))
            ->exists();
    }

    /**
     * @return Collection<int, Visit>
     */
    public function visits(Patient $patient, User $doctor, Organization $organization): Collection
    {
        if ($patient->email === null || ! $this->hasConsent($patient, $doctor, $organization)) {
            return collect();
        }

        $otherPatientIds = Patient::withoutTenancy()
            ->where('email', $patient->email)
            ->where('name', $patient->name)
            ->where('organization_id', '!=', $organization->id)
            ->pluck('id');

        $visits = Visit::withoutTenancy()
            ->whereIn('patient_id', $otherPatientIds)
            ->with(['organization', 'doctor:id,name'])
            ->latest('visited_on')
            ->limit(50)
            ->get();

        foreach ($visits->groupBy('organization_id') as $sourceOrganizationId => $rows) {
            $this->audit->record('patients.cross_org_viewed', null, [
                'source_organization_id' => $sourceOrganizationId,
                'visits' => $rows->count(),
                'patient_id' => $patient->id,
            ]);
            $this->audit->record('patients.record_shared', null, [
                'with_organization_id' => $organization->id,
                'visits' => $rows->count(),
            ], (int) $sourceOrganizationId);
        }

        return $visits->toBase();
    }
}
