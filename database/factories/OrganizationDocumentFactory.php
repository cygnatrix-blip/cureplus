<?php

namespace Database\Factories;

use App\Models\OrganizationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationDocument>
 */
class OrganizationDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'clinic_proof',
            'path' => 'verification/test/'.fake()->uuid().'.pdf',
            'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ];
    }
}
