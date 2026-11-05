<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Platform default EMR templates. Safe for production and repeatable.
 */
class DefaultTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['General Physician', 'Fever', ['complaints' => 'Fever for __ days, with / without chills.', 'history' => 'No known chronic illness.', 'diagnosis' => 'Acute febrile illness']],
            ['General Physician', 'Diabetes follow-up', ['complaints' => 'Routine diabetes review.', 'history' => 'Type 2 diabetes for __ years.', 'diagnosis' => 'Type 2 diabetes mellitus']],
            ['Pediatrician', 'Child wellness', ['complaints' => 'Routine check-up.', 'history' => 'Immunisation up to date: yes / no.', 'diagnosis' => '']],
            ['Dermatologist', 'Skin rash', ['complaints' => 'Rash on __ for __ days, itchy / non-itchy.', 'history' => 'No new medicines or cosmetics.', 'diagnosis' => '']],
            ['ENT', 'Sore throat', ['complaints' => 'Sore throat for __ days.', 'history' => '', 'diagnosis' => 'Acute pharyngitis']],
        ];

        foreach ($templates as [$specialty, $name, $body]) {
            Template::withoutTenancy()->firstOrCreate(
                ['organization_id' => null, 'kind' => 'emr', 'name' => $name],
                ['specialty' => $specialty, 'body' => $body],
            );
        }
    }
}
