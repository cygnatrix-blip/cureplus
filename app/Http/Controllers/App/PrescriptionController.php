<?php

namespace App\Http\Controllers\App;

use App\Actions\SavePrescription;
use App\Http\Controllers\Controller;
use App\Jobs\SendPrescriptionEmail;
use App\Models\DoctorProfile;
use App\Models\FavouriteMedicine;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\OrganizationMail;
use App\Services\PrescriptionPdf;
use App\Support\DocumentLink;
use App\Support\Specialties;
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
            'isAllied' => Specialties::isAllied(DoctorProfile::query()->where('user_id', $visit->doctor_user_id)->value('specialty')),
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

        $outOfScope = $this->outsideProfession($visit, $validator->validated()['items']);

        if ($outOfScope !== null) {
            throw ValidationException::withMessages(['items_json' => "{$outOfScope} is outside your profession, so it cannot go on your plan. Please refer the patient to a doctor."]);
        }

        $prescription = $save($visit, ['items' => $validator->validated()['items']] + $data);

        if (! app(TenantContext::class)->organization()?->isActive()) {
            return back()->with('success', 'Prescription saved. Printing opens once your account is verified.');
        }

        return redirect()->route('app.prescriptions.show', $prescription)->with('success', 'Prescription saved.');
    }

    /**
     * Owners see the whole practice, and a doctor the ones they wrote. Anyone else an owner
     * has allowed in sees the branches they work at.
     */
    public function index(Request $request, TenantContext $tenant): View
    {
        $membership = $tenant->membership();
        $isOwner = $membership?->hasRole('owner') ?? false;
        $onlyOwn = ! $isOwner && ($membership?->hasRole('doctor') ?? false);
        $search = trim((string) $request->query('q'));
        $doctorId = $request->integer('doctor') ?: null;
        $from = $this->validDate($request->query('from'));
        $to = $this->validDate($request->query('to'));

        $visible = Prescription::query()
            ->when($onlyOwn, fn ($query) => $query->where('doctor_user_id', $request->user()->id))
            ->when(! $isOwner && ! $onlyOwn, fn ($query) => $query->whereIn('location_id', $membership?->locations->pluck('id') ?? []));

        return view('app.prescriptions.index', [
            'prescriptions' => (clone $visible)
                ->with(['patient:id,name,patient_code', 'location:id,name'])
                ->when($search !== '', fn ($query) => $query->whereHas('patient', fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('patient_code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")))
                ->when($doctorId && ! $onlyOwn, fn ($query) => $query->where('doctor_user_id', $doctorId))
                ->when($from, fn ($query) => $query->whereDate('issued_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('issued_at', '<=', $to))
                ->latest('issued_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'doctors' => (clone $visible)->select('doctor_user_id', 'doctor_name')->distinct()->orderBy('doctor_name')->get(),
            'onlyOwn' => $onlyOwn,
            'search' => $search,
            'doctorId' => $doctorId,
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    public function show(Prescription $prescription, AuditLogger $audit): View
    {
        $audit->record('prescriptions.viewed', $prescription);
        $prescription->load(['items', 'patient', 'location', 'visit:id,diagnosis']);

        return view('app.prescriptions.show', ['prescription' => $prescription, 'shareLink' => DocumentLink::prescription($prescription->id)]);
    }

    public function pdf(Prescription $prescription, PrescriptionPdf $pdf, AuditLogger $audit): Response
    {
        $audit->record('prescriptions.downloaded', $prescription);

        return response($pdf->render($prescription), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($prescription).'"',
        ]);
    }

    public function email(Prescription $prescription, TenantContext $tenant, OrganizationMail $mail): RedirectResponse
    {
        $prescription->load('patient:id,email');

        if (! $prescription->patient->email) {
            return back()->with('error', 'This patient has no email address on file.');
        }

        if (! $mail->hasOwnEmail((int) $tenant->id())) {
            return back()->with('error', 'Connect your own email under Integrations first. Patient emails are sent from your address.');
        }

        SendPrescriptionEmail::dispatch((int) $tenant->id(), $prescription->id);

        return back()->with('success', 'Prescription queued for email.');
    }

    /**
     * Typed text is checked too, since otherwise it would be an easy way round the search.
     * Exercises and diet advice match nothing in the catalogue, so they pass.
     *
     * @param  array<int, array{medicine_id?: int|null, medicine_name: string}>  $items
     */
    private function outsideProfession(Visit $visit, array $items): ?string
    {
        $specialty = DoctorProfile::query()->where('user_id', $visit->doctor_user_id)->value('specialty');

        if (! Specialties::isAllied($specialty)) {
            return null;
        }

        $allowed = Medicine::query()->withinScopeOf($specialty)->pluck('generic_name', 'id');
        $allowedNames = $allowed->map(fn (string $name) => mb_strtolower($name))->unique()->flip();
        $otherNames = Medicine::query()->whereNotIn('id', $allowed->keys())->pluck('generic_name')->map(fn (string $name) => mb_strtolower($name))->unique()->reject(fn (string $name) => $allowedNames->has($name));

        foreach ($items as $item) {
            $name = mb_strtolower(trim($item['medicine_name']));

            if (! empty($item['medicine_id']) && ! $allowed->has((int) $item['medicine_id'])) {
                return $item['medicine_name'];
            }

            if ($otherNames->contains(fn (string $other) => $name === $other || str_starts_with($name, "{$other} "))) {
                return $item['medicine_name'];
            }
        }

        return null;
    }

    public function searchMedicines(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $favouriteIds = FavouriteMedicine::query()->pluck('medicine_id')->flip();

        $specialty = $request->user()->doctorProfile?->specialty;

        $medicines = Medicine::query()->where('is_active', true)->search($term)->withinScopeOf($specialty)->rankedFor($specialty, $term)->limit(25)->get()
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
