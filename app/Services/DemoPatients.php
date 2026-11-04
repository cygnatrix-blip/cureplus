<?php

namespace App\Services;

use App\Actions\RegisterPatient;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sample patients so a new practice can try the product while it waits for approval.
 * They have no email and a reserved number, so nothing can ever reach a real person.
 */
class DemoPatients
{
    private const SAMPLES = [
        ['Sample Patient One', '+919000000001', 42, 'male'],
        ['Sample Patient Two', '+919000000002', 35, 'female'],
        ['Sample Patient Three', '+919000000003', 8, 'female'],
    ];

    public function __construct(private readonly RegisterPatient $register) {}

    public function create(int $organizationId): void
    {
        if (Patient::withoutTenancy()->where('organization_id', $organizationId)->where('is_demo', true)->exists()) {
            return;
        }

        foreach (self::SAMPLES as [$name, $mobile, $age, $gender]) {
            ($this->register)($organizationId, ['name' => $name, 'mobile' => $mobile, 'age' => $age, 'gender' => $gender, 'is_demo' => true]);
        }
    }

    /**
     * Practice records must not be mixed into a clinic's real ones, so approval removes
     * them completely, and numbering starts again so the first real patient is P-000001.
     * Database foreign keys protect the order: anything left behind stops the delete.
     */
    public function purge(int $organizationId): void
    {
        $patientIds = Patient::withoutTenancy()->withTrashed()->where('organization_id', $organizationId)->where('is_demo', true)->pluck('id');

        if ($patientIds->isEmpty()) {
            return;
        }

        $visitIds = DB::table('visits')->whereIn('patient_id', $patientIds)->pluck('id');
        $disk = Storage::disk(config('filesystems.default'));

        foreach (DB::table('visit_attachments')->whereIn('visit_id', $visitIds)->pluck('path') as $path) {
            $disk->delete($path);
        }

        DB::table('visit_attachments')->whereIn('visit_id', $visitIds)->delete();
        DB::table('prescriptions')->whereIn('visit_id', $visitIds)->delete();
        DB::table('follow_ups')->whereIn('patient_id', $patientIds)->delete();
        DB::table('visits')->whereIn('id', $visitIds)->delete();
        DB::table('appointments')->whereIn('patient_id', $patientIds)->delete();
        DB::table('patients')->whereIn('id', $patientIds)->delete();
        DB::table('organization_counters')->where('organization_id', $organizationId)->delete();
    }
}
