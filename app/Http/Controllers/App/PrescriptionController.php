<?php

namespace App\Http\Controllers\App;

use App\Actions\SavePrescription;
use App\Http\Controllers\Controller;
use App\Jobs\SendPrescriptionEmail;
use App\Models\FavouriteMedicine;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\PrescriptionPdf;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function edit(Visit $visit, Request $request): View
    {
        $visit->load(['patient', 'prescription.items', 'doctor:id,name']);
        $prescription = $visit->prescription;
        $items = $prescription?->items;

        // "Copy last Rx": start from this patient's previous prescription.
        if ($request->boolean('copy_last')) {
            $last = Prescription::query()
                ->where('patient_id', $visit->patient_id)
                ->where('visit_id', '!=', $visit->id)
                ->with('items')
                ->latest('id')
                ->first();
            $items = $last->items ?? $items;
        }

        return view('app.prescriptions.edit', [
            'visit' => $visit,
            'prescription' => $prescription,
            'items' => collect($items)->map(fn ($item) => [
                'medicine_id' => $item->medicine_id,
                'medicine_name' => $item->medicine_name,
                'dosage' => $item->dosage,
                'timing' => $item->timing,
                'duration_days' => $item->duration_days,
                'notes' => $item->notes,
            ])->values(),
        ]);
    }

    public function update(Request $request, Visit $visit, SavePrescription $save): RedirectResponse
    {
        abort_unless($visit->isOpen(), 422, 'This consultation is closed.');

        $data = $request->validate([
            'items_json' => ['required', 'json'],
            'lab_advice' => ['nullable', 'string', 'max:3000'],
            'patient_instructions' => ['nullable', 'string', 'max:3000'],
            'instructions_language' => ['required', 'in:en,hi,bn'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $validator = Validator::make(['items' => json_decode($data['items_json'], true)], [
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'items.*.medicine_name' => ['required', 'string', 'max:255'],
            'items.*.dosage' => ['nullable', 'string', 'max:30'],
            'items.*.timing' => ['nullable', 'string', 'max:30'],
            'items.*.duration_days' => ['nullable', 'integer', 'between:1,365'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ], ['items.required' => 'Add at least one medicine.', 'items.min' => 'Add at least one medicine.']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['items_json' => $validator->errors()->first()]);
        }

        $prescription = $save($visit, ['items' => $validator->validated()['items']] + $data);

        return redirect()->route('app.prescriptions.show', $prescription)->with('success', 'Prescription saved.');
    }

    public function show(Prescription $prescription, AuditLogger $audit): View
    {
        $audit->record('prescriptions.viewed', $prescription);
        $prescription->load(['items', 'patient', 'location', 'visit:id,diagnosis']);

        return view('app.prescriptions.show', ['prescription' => $prescription]);
    }

    public function pdf(Prescription $prescription, PrescriptionPdf $pdf, AuditLogger $audit): Response
    {
        $audit->record('prescriptions.downloaded', $prescription);

        return response($pdf->render($prescription), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($prescription).'"',
        ]);
    }

    public function email(Prescription $prescription, TenantContext $tenant): RedirectResponse
    {
        $prescription->load('patient:id,email');

        if (! $prescription->patient->email) {
            return back()->with('error', 'This patient has no email address on file.');
        }

        SendPrescriptionEmail::dispatch((int) $tenant->id(), $prescription->id);

        return back()->with('success', 'Prescription queued for email.');
    }

    public function searchMedicines(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $favouriteIds = FavouriteMedicine::query()->pluck('medicine_id')->flip();

        $medicines = Medicine::query()->where('is_active', true)->search($term)->limit(25)->get()
            ->sortByDesc(fn (Medicine $medicine) => $favouriteIds->has($medicine->id))
            ->take(15)
            ->values();

        return response()->json($medicines->map(fn (Medicine $medicine) => [
            'id' => $medicine->id,
            'name' => $medicine->displayName(),
            'generic' => $medicine->generic_name,
            'form' => $medicine->form,
            'favourite' => $favouriteIds->has($medicine->id),
        ]));
    }
}
