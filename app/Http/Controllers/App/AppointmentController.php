<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\AuditLogger;
use App\Services\DoctorCalendar;
use App\Services\Messaging\AppointmentMessages;
use App\Services\TenantDoctors;
use App\Services\VideoConsultation;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly DoctorCalendar $calendar,
        private readonly TenantDoctors $doctors,
        private readonly TenantContext $tenant,
        private readonly VideoConsultation $video,
        private readonly AuditLogger $audit,
        private readonly AppointmentMessages $messages,
    ) {}

    public function index(Request $request): View
    {
        $date = $this->date($request->query('date'));
        $doctorId = $request->integer('doctor') ?: null;
        $status = AppointmentStatus::tryFrom((string) $request->query('status'));

        return view('app.appointments.index', [
            'appointments' => Appointment::query()
                ->where('location_id', $this->tenant->location()?->id)
                ->whereDate('scheduled_on', $date)
                ->when($doctorId, fn ($query) => $query->where('doctor_user_id', $doctorId))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->with(['patient:id,name,mobile,patient_code', 'doctor:id,name'])
                ->orderBy('token_number')
                ->paginate(50)
                ->withQueryString(),
            'date' => $date,
            'doctorId' => $doctorId,
            'status' => $status,
            'doctors' => $this->doctors->all($this->tenant->location()?->id),
        ]);
    }

    public function create(Request $request): View
    {
        $locationId = (int) $this->tenant->location()?->id;
        $date = $this->date($request->query('date'));
        $doctorId = $request->integer('doctor') ?: null;
        $doctors = $this->doctors->all($locationId);

        return view('app.appointments.create', [
            'patient' => $request->integer('patient') ? Patient::query()->findOrFail($request->integer('patient')) : null,
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'date' => $date,
            'offersVideo' => $doctorId && $this->video->offeredBy($doctorId),
            'slots' => $doctorId && $doctors->contains('id', $doctorId) ? $this->calendar->slots($doctorId, (int) $this->tenant->id(), $locationId, $date) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedBooking($request);
        $patient = Patient::query()->findOrFail($data['patient_id']);
        $location = (int) $this->tenant->location()?->id;

        if ($request->boolean('walk_in')) {
            $appointment = $this->appointments->walkIn($patient, $data['doctor_user_id'], $location, VisitType::from($data['visit_type']));

            return redirect()->route('app.queue.index')->with('success', "Token {$appointment->token_number} for {$patient->name}.");
        }

        $startsAt = $this->slot($request);
        $mode = AppointmentMode::from($request->validate(['mode' => ['nullable', Rule::enum(AppointmentMode::class)]])['mode'] ?? AppointmentMode::InPerson->value);

        if ($mode === AppointmentMode::Online && ! $this->video->offeredBy($data['doctor_user_id'])) {
            throw ValidationException::withMessages(['mode' => 'This doctor has not turned on video calls.']);
        }

        // The telemedicine guidelines want explicit consent when the clinic starts the consultation.
        if ($mode === AppointmentMode::Online && ! $request->boolean('video_consent')) {
            throw ValidationException::withMessages(['video_consent' => 'Please confirm the patient has agreed to a video call.']);
        }

        try {
            $appointment = $this->appointments->bookSlot($patient, $data['doctor_user_id'], $location, $startsAt, VisitType::from($data['visit_type']), mode: $mode);
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        if ($mode === AppointmentMode::Online) {
            $this->audit->record('appointments.video_consent_recorded', $appointment);
        }

        return redirect()->route('app.appointments.index', ['date' => $startsAt->toDateString()])
            ->with('success', "Booked. Token {$appointment->token_number}.");
    }

    public function edit(Request $request, Appointment $appointment): View
    {
        $date = $this->date($request->query('date', $appointment->scheduled_on->toDateString()));
        $appointment->load(['patient:id,name,mobile', 'doctor:id,name']);

        return view('app.appointments.reschedule', [
            'appointment' => $appointment,
            'date' => $date,
            'slots' => $this->calendar->slots((int) $appointment->doctor_user_id, (int) $appointment->organization_id, (int) $appointment->location_id, $date),
        ]);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->status->holdsSlot() && $appointment->status !== AppointmentStatus::Completed, 422, 'This appointment can no longer be changed.');

        try {
            $this->appointments->reschedule($appointment, $this->slot($request));
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        $this->messages->send($appointment, 'appointment_moved', (string) $appointment->slot_key);

        return redirect()->route('app.appointments.index', ['date' => $appointment->scheduled_on->toDateString()])->with('success', 'Rescheduled.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->appointments->cancel($appointment, $data['reason']);
        $this->messages->send($appointment, 'appointment_cancelled');

        return back()->with('success', 'Appointment cancelled.');
    }

    /**
     * @return array{patient_id: int, doctor_user_id: int, visit_type: string}
     */
    private function validatedBooking(Request $request): array
    {
        $data = $request->validate([
            // Scoped by hand: Rule::exists queries the table directly and ignores the tenant.
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where('organization_id', $this->tenant->id())->whereNull('deleted_at')],
            'doctor_user_id' => ['required', 'integer'],
            'visit_type' => ['required', Rule::enum(VisitType::class)],
        ]);

        if (! $this->doctors->all($this->tenant->location()?->id)->contains('id', (int) $data['doctor_user_id'])) {
            throw ValidationException::withMessages(['doctor_user_id' => 'This doctor does not work at this location.']);
        }

        return ['patient_id' => (int) $data['patient_id'], 'doctor_user_id' => (int) $data['doctor_user_id'], 'visit_type' => $data['visit_type']];
    }

    private function slot(Request $request): CarbonImmutable
    {
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d H:i']]);

        return CarbonImmutable::createFromFormat('Y-m-d H:i', $data['starts_at'])->startOfMinute();
    }

    private function date(mixed $value): CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay()
            : CarbonImmutable::today();
    }
}
