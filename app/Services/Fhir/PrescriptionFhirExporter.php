<?php

namespace App\Services\Fhir;

use App\Models\Prescription;

/**
 * Prescriptions as FHIR R4 MedicationRequest resources, the format ABDM's Health
 * Information Exchange builds on. This is readiness: it has not been certified against
 * ABDM's sandbox, and the medicine codes are text because the catalogue has no SNOMED codes.
 */
class PrescriptionFhirExporter
{
    /**
     * @return array<string, mixed>
     */
    public function bundle(Prescription $prescription): array
    {
        $prescription->loadMissing(['items', 'patient']);

        $identifiers = [['system' => 'cureplus:patient-code', 'value' => $prescription->patient->patient_code]];

        if ($prescription->patient->abha_id) {
            $identifiers[] = ['system' => 'https://healthid.ndhm.gov.in', 'value' => $prescription->patient->abha_id];
        }

        return [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'timestamp' => $prescription->issued_at?->toIso8601String(),
            'entry' => [
                ['resource' => [
                    'resourceType' => 'Patient',
                    'id' => 'patient-'.$prescription->patient_id,
                    'identifier' => $identifiers,
                    'name' => [['text' => $prescription->patient->name]],
                    'gender' => $prescription->patient->gender ?? 'unknown',
                ]],
                ['resource' => [
                    'resourceType' => 'Practitioner',
                    'id' => 'practitioner-'.$prescription->doctor_user_id,
                    'identifier' => $prescription->doctor_registration_number ? [['system' => 'cureplus:medical-registration', 'value' => $prescription->doctor_registration_number]] : [],
                    'name' => [['text' => $prescription->doctor_name]],
                ]],
                ...$prescription->items->map(fn ($item) => ['resource' => [
                    'resourceType' => 'MedicationRequest',
                    'id' => 'rx-item-'.$item->id,
                    'status' => 'active',
                    'intent' => 'order',
                    'medicationCodeableConcept' => ['text' => $item->medicine_name],
                    'subject' => ['reference' => 'Patient/patient-'.$prescription->patient_id],
                    'requester' => ['reference' => 'Practitioner/practitioner-'.$prescription->doctor_user_id],
                    'authoredOn' => $prescription->issued_at?->toDateString(),
                    'dosageInstruction' => [['text' => trim($item->dosage.' '.$item->timing.' '.($item->duration_days ? "{$item->duration_days} days" : ''))]],
                ]])->all(),
            ],
        ];
    }
}
