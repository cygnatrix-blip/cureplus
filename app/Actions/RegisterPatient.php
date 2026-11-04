<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\Patient;
use App\Services\SequenceService;
use Illuminate\Auth\Access\AuthorizationException;

class RegisterPatient
{
    public function __construct(private readonly SequenceService $sequence) {}

    /**
     * @param  array{name: string, mobile: string, is_demo?: bool, age?: int|null, date_of_birth?: string|null, gender?: string|null, email?: string|null, relation?: string|null, address?: string|null, abha_id?: string|null, allergies?: string|null}  $data
     */
    public function __invoke(int $organizationId, array $data): Patient
    {
        $isDemo = (bool) ($data['is_demo'] ?? false);

        // Checked here as well as on the routes: no path may add a real patient before approval.
        if (! $isDemo && ! Organization::query()->findOrFail($organizationId)->isActive()) {
            throw new AuthorizationException('This practice is not approved yet.');
        }

        $estimated = empty($data['date_of_birth']) && isset($data['age']);
        $dateOfBirth = $estimated ? now()->subYears((int) $data['age'])->toDateString() : ($data['date_of_birth'] ?? null);
        $number = $this->sequence->next($organizationId, 'patient');

        $patient = Patient::withoutTenancy()->create([
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

        if ($isDemo) {
            $patient->forceFill(['is_demo' => true])->save();
        }

        return $patient;
    }
}
