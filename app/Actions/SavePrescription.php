<?php

namespace App\Actions;

use App\Enums\AppointmentMode;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\FollowUp;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class SavePrescription
{
    /**
     * @param  array{items: list<array{medicine_id?: int|null, medicine_name: string, dosage?: string|null, timing?: string|null, duration_days?: int|null, notes?: string|null}>, lab_advice?: string|null, patient_instructions?: string|null, instructions_language?: string|null, follow_up_on?: string|null}  $data
     */
    public function __invoke(Visit $visit, array $data): Prescription
    {
        return DB::transaction(function () use ($visit, $data): Prescription {
            $profile = DoctorProfile::query()->where('user_id', $visit->doctor_user_id)->first();
            $visit->loadMissing('doctor:id,name');

            $prescription = Prescription::query()->updateOrCreate(['visit_id' => $visit->id], [
                'patient_id' => $visit->patient_id,
                'location_id' => $visit->location_id,
                'doctor_user_id' => $visit->doctor_user_id,
                'doctor_name' => $visit->doctor->name,
                'doctor_qualification' => $profile?->qualification,
                'doctor_specialty' => $profile?->specialty,
                'is_teleconsultation' => $visit->appointment_id !== null && Appointment::withoutTenancy()->whereKey($visit->appointment_id)->value('mode') === AppointmentMode::Online,
                'doctor_registration_number' => $profile?->registration_number,
                'lab_advice' => $data['lab_advice'] ?? null,
                'patient_instructions' => $data['patient_instructions'] ?? null,
                'instructions_language' => $data['instructions_language'] ?? 'en',
                'follow_up_on' => $data['follow_up_on'] ?? null,
                'issued_at' => now(),
            ]);

            $prescription->items()->delete();

            foreach ($data['items'] as $position => $item) {
                $prescription->items()->create($item + ['position' => $position, 'organization_id' => $visit->organization_id]);
            }

            $this->syncFollowUp($visit, $data['follow_up_on'] ?? null);

            return $prescription;
        });
    }

    private function syncFollowUp(Visit $visit, ?string $dueOn): void
    {
        $existing = FollowUp::query()->where('visit_id', $visit->id)->where('status', 'pending')->first();

        if ($dueOn === null) {
            $existing?->update(['status' => 'cancelled']);

            return;
        }

        FollowUp::query()->updateOrCreate(['visit_id' => $visit->id, 'status' => 'pending'], [
            'patient_id' => $visit->patient_id,
            'doctor_user_id' => $visit->doctor_user_id,
            'due_on' => $dueOn,
        ]);
    }
}
