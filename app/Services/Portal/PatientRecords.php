<?php

namespace App\Services\Portal;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consent;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\VisitAttachment;
use Illuminate\Support\Collection;

/**
 * Everything the portal shows goes through here. There is no tenant in a patient's
 * session, so each query opts out of the tenant scope and is pinned instead to the
 * patient records linked to this account. No other portal query reads clinical tables.
 */
class PatientRecords
{
    /**
     * Connects records with this email to the account. Runs after OTP login, so the
     * person has just proved they hold the address. Records stay separate rows.
     */
    public function link(PatientAccount $account): void
    {
        Patient::withoutTenancy()
            ->where('email', $account->email)
            ->whereNull('patient_account_id')
            ->update(['patient_account_id' => $account->id]);
    }

    /**
     * @return Collection<int, Patient>
     */
    public function patients(PatientAccount $account): Collection
    {
        return Patient::withoutTenancy()->where('patient_account_id', $account->id)->with('organization')->orderBy('name')->get()->toBase();
    }

    /**
     * @return list<int>
     */
    public function patientIds(PatientAccount $account): array
    {
        return Patient::withoutTenancy()->where('patient_account_id', $account->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function upcomingAppointments(PatientAccount $account): Collection
    {
        return $this->appointments($account)
            ->filter(fn (Appointment $appointment) => $appointment->scheduled_on->gte(today()) && in_array($appointment->status, [AppointmentStatus::Booked, AppointmentStatus::CheckedIn, AppointmentStatus::InConsultation], true))
            ->sortBy('scheduled_on')
            ->values();
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function appointments(PatientAccount $account): Collection
    {
        return Appointment::withoutTenancy()
            ->whereIn('patient_id', $this->patientIds($account))
            ->with($this->appointmentRelations())
            ->latest('scheduled_on')
            ->limit(100)
            ->get()
            ->toBase();
    }

    public function appointment(PatientAccount $account, int $id): Appointment
    {
        return Appointment::withoutTenancy()->whereIn('patient_id', $this->patientIds($account))->with($this->appointmentRelations())->findOrFail($id);
    }

    /**
     * @return Collection<int, Prescription>
     */
    public function prescriptions(PatientAccount $account): Collection
    {
        return Prescription::withoutTenancy()
            ->whereIn('patient_id', $this->patientIds($account))
            ->with(['organization'])
            ->latest('issued_at')
            ->limit(100)
            ->get()
            ->toBase();
    }

    public function prescription(PatientAccount $account, int $id): Prescription
    {
        return Prescription::withoutTenancy()->whereIn('patient_id', $this->patientIds($account))->findOrFail($id);
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function invoices(PatientAccount $account): Collection
    {
        return Invoice::withoutTenancy()
            ->whereIn('patient_id', $this->patientIds($account))
            ->with(['organization'])
            ->latest('issued_at')
            ->limit(100)
            ->get()
            ->toBase();
    }

    public function invoice(PatientAccount $account, int $id): Invoice
    {
        return Invoice::withoutTenancy()->whereIn('patient_id', $this->patientIds($account))->findOrFail($id);
    }

    /**
     * @return Collection<int, VisitAttachment>
     */
    public function reports(PatientAccount $account): Collection
    {
        $visitIds = Visit::withoutTenancy()->whereIn('patient_id', $this->patientIds($account))->pluck('id');

        return VisitAttachment::withoutTenancy()
            ->whereIn('visit_id', $visitIds)
            ->where('patient_visible', true)
            ->with('organization')
            ->latest('id')
            ->limit(100)
            ->get()
            ->toBase();
    }

    public function report(PatientAccount $account, int $id): VisitAttachment
    {
        $visitIds = Visit::withoutTenancy()->whereIn('patient_id', $this->patientIds($account))->pluck('id');

        return VisitAttachment::withoutTenancy()->whereIn('visit_id', $visitIds)->where('patient_visible', true)->findOrFail($id);
    }

    /**
     * The DPDP "download my data" file. Structured so another system could read it.
     *
     * @return array<string, mixed>
     */
    public function export(PatientAccount $account): array
    {
        $ids = $this->patientIds($account);
        $organizationNames = $this->patients($account)->mapWithKeys(fn (Patient $patient) => [$patient->id => $patient->organization?->name]);

        return [
            'generated_at' => now()->toIso8601String(),
            'account' => ['mobile' => $account->mobile, 'name' => $account->name, 'email' => $account->email, 'language' => $account->locale],
            'patient_records' => $this->patients($account)->map(fn (Patient $patient) => [
                'organization' => $patient->organization?->name,
                'patient_id' => $patient->patient_code,
                'name' => $patient->name,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'gender' => $patient->gender,
                'abha_number' => $patient->abha_id,
                'allergies' => $patient->allergies,
            ])->values(),
            'appointments' => Appointment::withoutTenancy()->whereIn('patient_id', $ids)->get()->map(fn (Appointment $appointment) => [
                'organization' => $organizationNames[$appointment->patient_id] ?? null,
                'date' => $appointment->scheduled_on->toDateString(),
                'status' => $appointment->status->value,
            ])->values(),
            'visits' => Visit::withoutTenancy()->whereIn('patient_id', $ids)->get()->map(fn (Visit $visit) => [
                'organization' => $organizationNames[$visit->patient_id] ?? null,
                'date' => $visit->visited_on->toDateString(),
                'complaints' => $visit->complaints,
                'diagnosis' => $visit->diagnosis,
            ])->values(),
            'prescriptions' => Prescription::withoutTenancy()->whereIn('patient_id', $ids)->get()->map(fn (Prescription $prescription) => [
                'organization' => $organizationNames[$prescription->patient_id] ?? null,
                'doctor' => $prescription->doctor_name,
                'date' => $prescription->issued_at?->toDateString(),
                'instructions' => $prescription->patient_instructions,
            ])->values(),
            'bills' => Invoice::withoutTenancy()->whereIn('patient_id', $ids)->get()->map(fn (Invoice $invoice) => [
                'organization' => $organizationNames[$invoice->patient_id] ?? null,
                'number' => $invoice->number,
                'date' => $invoice->issued_at->toDateString(),
                'total_paise' => $invoice->total_paise,
                'paid_paise' => $invoice->paid_paise,
            ])->values(),
            'consents' => Consent::query()->where('patient_account_id', $account->id)->with('granteeOrganization:id,name')->get()->map(fn (Consent $consent) => [
                'purpose' => $consent->purpose,
                'shared_with' => $consent->granteeOrganization?->name,
                'granted_at' => $consent->granted_at->toIso8601String(),
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * Relations that would otherwise be filtered to nothing by the tenant scope.
     *
     * @return array<int|string, mixed>
     */
    private function appointmentRelations(): array
    {
        return [
            'organization',
            'doctor:id,name',
            'patient' => fn ($query) => $query->withoutTenancy(),
            'location' => fn ($query) => $query->withoutTenancy(),
        ];
    }
}
