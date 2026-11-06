<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentMessage;
use App\Services\AppointmentService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QueueController extends Controller
{
    private const ALLOWED_MOVES = [
        'booked' => ['checked_in', 'in_consultation', 'no_show'],
        'checked_in' => ['in_consultation', 'no_show'],
        'in_consultation' => ['completed'],
        'no_show' => ['checked_in'],
    ];

    /** The oldest unfinished appointments past this many wait until newer ones are cleared. */
    private const EARLIER_LIMIT = 100;

    private const ROW_RELATIONS = ['patient:id,name,mobile,patient_code', 'doctor:id,name', 'visit:id,appointment_id'];

    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $view = $request->boolean('fragment') ? 'app.queue.board' : 'app.queue.index';

        return view($view, ['groups' => $this->board(), 'earlier' => $this->unfinishedEarlier(), 'location' => $this->tenant->location()]);
    }

    public function move(Request $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(AppointmentStatus::class)]]);
        $target = AppointmentStatus::from($data['status']);

        abort_unless(in_array($target->value, self::ALLOWED_MOVES[$appointment->status->value] ?? [], true), 422, 'That move is not possible from here.');

        $appointments->transition($appointment, $target);

        return $request->expectsJson() ? response()->json(['status' => $target->value]) : back();
    }

    /**
     * @return Collection<int|string, covariant Collection<int, Appointment>>
     */
    private function board(): Collection
    {
        return Appointment::query()
            ->where('location_id', $this->tenant->location()?->id)
            ->whereDate('scheduled_on', today())
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value])
            ->with(self::ROW_RELATIONS)
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query->where('sender_type', AppointmentMessage::FROM_PATIENT)->whereNull('read_at')])
            ->orderBy('token_number')
            ->get()
            ->groupBy('doctor_user_id');
    }

    /**
     * Appointments from past days that never reached an end: never checked in, left waiting,
     * or a consultation that was never completed. They stay on the board until someone
     * completes them or marks a no-show, so nothing is silently lost overnight.
     *
     * @return Collection<int, Appointment>
     */
    private function unfinishedEarlier(): Collection
    {
        return Appointment::query()
            ->where('location_id', $this->tenant->location()?->id)
            ->whereDate('scheduled_on', '<', today())
            ->whereIn('status', [AppointmentStatus::Booked->value, AppointmentStatus::CheckedIn->value, AppointmentStatus::InConsultation->value])
            ->with(self::ROW_RELATIONS)
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query->where('sender_type', AppointmentMessage::FROM_PATIENT)->whereNull('read_at')])
            ->orderByDesc('scheduled_on')
            ->orderBy('token_number')
            ->limit(self::EARLIER_LIMIT)
            ->get();
    }
}
