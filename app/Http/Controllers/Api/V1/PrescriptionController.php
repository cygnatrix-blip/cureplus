<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Prescription;
use App\Services\AuditLogger;
use App\Services\Fhir\PrescriptionFhirExporter;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    /**
     * For the e-prescription link to a pharmacy. The tenant scope keeps a key to its own
     * organization, and every read goes in that organization's audit log.
     */
    public function show(Request $request, int $id, TenantContext $tenant, PrescriptionFhirExporter $exporter, AuditLogger $audit): JsonResponse
    {
        abort_unless($tenant->has(), 403, 'This key is not linked to an organization.');

        $prescription = Prescription::query()->findOrFail($id);

        /** @var ApiKey $key */
        $key = $request->attributes->get('api_key');
        $audit->record('prescriptions.read_by_api', $prescription, ['api_key' => $key->prefix]);

        return response()->json($exporter->bundle($prescription));
    }
}
