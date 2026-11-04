<?php

namespace App\Http\Controllers\App;

use App\Actions\RegisterPatient;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use App\Rules\IndianMobile;
use App\Services\AuditLogger;
use App\Support\Csv;
use App\Support\Mobile;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('app.patients.index', [
            'patients' => Patient::query()
                ->when($search !== '', fn ($query) => $query->search($search))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function export(AuditLogger $audit): StreamedResponse
    {
        $audit->record('patients.exported');

        $rows = Patient::query()->latest('id')->limit(10000)->get()->map(fn (Patient $patient) => [
            $patient->patient_code, $patient->name, $patient->mobile, $patient->gender, $patient->ageInYears(), $patient->created_at?->toDateString(),
        ]);

        return Csv::download('patients.csv', ['Patient ID', 'Name', 'Mobile', 'Gender', 'Age', 'Registered'], $rows);
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(Patient::query()->search($term)->limit(10)->get(['id', 'patient_code', 'name', 'mobile'])->map(fn (Patient $patient) => [
            'id' => $patient->id,
            'label' => "{$patient->name} · {$patient->mobile} · {$patient->patient_code}",
        ]));
    }

    public function create(): View
    {
        return view('app.patients.form', ['patient' => new Patient, 'duplicates' => collect()]);
    }

    public function store(Request $request, RegisterPatient $register, TenantContext $tenant): RedirectResponse|View
    {
        $data = $this->validated($request);

        // Same number is normal (families share phones). Same number and name is probably a repeat.
        $duplicates = Patient::query()
            ->where('mobile', $data['mobile'])
            ->where('name', 'like', $data['name'])
            ->get();

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_new')) {
            $request->flash();

            return view('app.patients.form', ['patient' => new Patient, 'duplicates' => $duplicates]);
        }

        $patient = $register((int) $tenant->id(), $data);

        return redirect($request->input('next') === 'book'
            ? route('app.appointments.create', ['patient' => $patient->id])
            : route('app.patients.show', $patient))
            ->with('success', "Registered as {$patient->patient_code}.");
    }

    public function show(Patient $patient, Request $request, AuditLogger $audit): View
    {
        // Every look at a patient's record is logged, as the DPDP duties require.
        $audit->record('patients.viewed', $patient);

        $visits = Gate::check('consultations.manage')
            ? Visit::query()->where('patient_id', $patient->id)->with('doctor:id,name')->latest('visited_on')->paginate(10)
            : null;

        return view('app.patients.show', [
            'patient' => $patient,
            'family' => Patient::query()->where('mobile', $patient->mobile)->whereKeyNot($patient->id)->limit(20)->get(),
            'appointments' => $patient->appointments()->with('doctor:id,name')->latest('scheduled_on')->limit(10)->get(),
            'visits' => $visits,
        ]);
    }

    public function edit(Patient $patient): View
    {
        return view('app.patients.form', ['patient' => $patient, 'duplicates' => collect()]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $data = $this->validated($request);

        $patient->update([
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'gender' => $data['gender'] ?? null,
            'relation' => $data['relation'] ?? null,
            'address' => $data['address'] ?? null,
            'abha_id' => $data['abha_id'] ?? null,
            'abha_hash' => empty($data['abha_id']) ? null : Patient::hashAbha($data['abha_id']),
            'allergies' => $data['allergies'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? (isset($data['age']) ? now()->subYears((int) $data['age'])->toDateString() : $patient->date_of_birth),
            'dob_is_estimated' => empty($data['date_of_birth']) && isset($data['age']),
        ]);

        return redirect()->route('app.patients.show', $patient)->with('success', 'Patient updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge(['mobile' => Mobile::normalize((string) $request->input('mobile')) ?? $request->input('mobile')]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', new IndianMobile],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'email' => ['nullable', 'email', 'max:190'],
            'relation' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'abha_id' => ['nullable', 'regex:/^\d{2}-?\d{4}-?\d{4}-?\d{4}$/'],
            'allergies' => ['nullable', 'string', 'max:1000'],
        ], ['abha_id.regex' => 'An ABHA number has 14 digits.']);
    }
}
