<?php

namespace App\Actions;

use App\Models\Patient;
use App\Services\SequenceService;

class RegisterPatient
{
    public function __construct(private readonly SequenceService $sequence) {}

    /**
     * @param  array{name: string, mobile: string, age?: int|null, date_of_birth?: string|null, gender?: string|null, email?: string|null, relation?: string|null, address?: string|null, abha_id?: string|null, allergies?: string|null}  $data
     */
    public function __invoke(int $organizationId, array $data): Patient
    {
        $estimated = empty($data['date_of_birth']) && isset($data['age']);
        $dateOfBirth = $estimated ? now()->subYears((int) $data['age'])->toDateString() : ($data['date_of_birth'] ?? null);
        $number = $this->sequence->next($organizationId, 'patient');

        return Patient::withoutTenancy()->create([
            'organization_id' => $organizationId,
            'patient_code' => 'P-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'date_of_birth' => $dateOfBirth,
            'dob_is_estimated' => $estimated,
            'gender' => $data['gender'] ?? null,
            'relation' => $data['relation'] ?? null,
            'address' => $data['address'] ?? null,
            'abha_id' => $data['abha_id'] ?? null,
            'abha_hash' => empty($data['abha_id']) ? null : Patient::hashAbha($data['abha_id']),
            'allergies' => $data['allergies'] ?? null,
        ]);
    }
}
