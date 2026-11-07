<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Template;
use App\Models\Visit;
use App\Models\VisitAttachment;
use App\Models\Vital;
use App\Services\AppointmentService;
use App\Services\AuditLogger;
use App\Services\Storage\DriveFiles;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * One visit per appointment: pressing Start twice reopens the same one.
     */
    public function start(Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        abort_if($appointment->status === AppointmentStatus::Cancelled, 422);

        // An owner can open a consultation the doctor has begun, but not begin one.
        $existing = Visit::query()->where('appointment_id', $appointment->id)->first();

        if (! Gate::check('start', [Visit::class, $appointment])) {
            return $existing
                ? redirect()->route('app.visits.show', $existing)
                : back()->with('error', 'Only this patient\'s doctor can start the consultation.');
        }

        $visit = Visit::query()->firstOrCreate(['appointment_id' => $appointment->id], [
            'location_id' => $appointment->location_id,
            'patient_id' => $appointment->patient_id,
            'doctor_user_id' => $appointment->doctor_user_id,
            'visited_on' => today()->toDateString(),
            'status' => 'open',
        ]);

        // A nurse opens the visit to record vitals; only a clinician moves the queue on.
        if ((int) $appointment->doctor_user_id === (int) Auth::id() && Gate::check('consultations.manage') && $appointment->status !== AppointmentStatus::InConsultation && $appointment->status !== AppointmentStatus::Completed) {
            $appointments->transition($appointment, AppointmentStatus::InConsultation);
        }

        return redirect()->route('app.visits.show', $visit);
    }

    public function show(Visit $visit): View
    {
        $this->audit->record('visits.viewed', $visit);
        $visit->load(['patient', 'doctor:id,name', 'vital', 'prescription', 'attachments']);

        return view('app.visits.show', [
            'visit' => $visit,
            'canViewNotes' => Gate::check('viewNotes', $visit),
            'canEdit' => Gate::check('update', $visit) && $visit->isOpen(),
            'pastVisits' => Visit::query()->where('patient_id', $visit->patient_id)->whereKeyNot($visit->id)->with('doctor:id,name')->latest('visited_on')->limit(10)->get(),
            'templates' => Template::query()->available('emr')->orderBy('name')->get(),
            'attachmentLinks' => $visit->attachments->mapWithKeys(fn (VisitAttachment $attachment) => [
                // Short-lived: a copied link stops working, even for the person who copied it.
                $attachment->id => URL::temporarySignedRoute('app.attachments.show', now()->addMinutes(15), ['attachment' => $attachment->id]),
            ]),
        ]);
    }

    public function update(Request $request, Visit $visit): RedirectResponse
    {
        abort_unless($visit->isOpen(), 422, 'This consultation is closed.');

        $visit->update($request->validate([
            'complaints' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('success', 'Saved.');
    }

    public function storeVitals(Request $request, Visit $visit): RedirectResponse
    {
        $data = $request->validate([
            'bp_systolic' => ['nullable', 'integer', 'between:40,300'],
            'bp_diastolic' => ['nullable', 'integer', 'between:20,200'],
            'pulse' => ['nullable', 'integer', 'between:20,300'],
            'temperature_f' => ['nullable', 'numeric', 'between:80,115'],
            'spo2' => ['nullable', 'integer', 'between:30,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.2,400'],
            'height_cm' => ['nullable', 'numeric', 'between:20,260'],
        ]);

        Vital::query()->updateOrCreate(['visit_id' => $visit->id], $data + ['recorded_by' => $request->user()->id]);

        return back()->with('success', 'Vitals saved.');
    }

    public function complete(Visit $visit, AppointmentService $appointments): RedirectResponse
    {
        $visit->update(['status' => 'completed', 'completed_at' => now()]);

        $appointment = Appointment::query()->find($visit->appointment_id);

        if ($appointment && $appointment->status === AppointmentStatus::InConsultation) {
            $appointments->transition($appointment, AppointmentStatus::Completed);
        }

        return redirect()->route('app.queue.index')->with('success', 'Consultation completed.');
    }

    public function storeAttachment(Request $request, Visit $visit, TenantContext $tenant): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'category' => ['required', 'in:lab_report,imaging,other'],
        ]);

        $file = $request->file('file');
        // The stored name and extension come from us and the real content type, never the client's filename.
        $path = $file->storeAs('clinical/'.$tenant->id(), Str::uuid().'.'.$file->extension(), config('filesystems.default'));

        VisitAttachment::create([
            'visit_id' => $visit->id,
            'disk' => config('filesystems.default'),
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'category' => $request->input('category'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'File attached.');
    }

    public function downloadAttachment(VisitAttachment $attachment): StreamedResponse
    {
        $this->audit->record('visit_attachments.viewed', $attachment);

        return app(DriveFiles::class)->serve($attachment);
    }
}
