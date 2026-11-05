<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
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

    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $view = $request->boolean('fragment') ? 'app.queue.board' : 'app.queue.index';

        return view($view, ['groups' => $this->board(), 'location' => $this->tenant->location()]);
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
            ->with(['patient:id,name,mobile,patient_code', 'doctor:id,name', 'visit:id,appointment_id'])
            ->orderBy('token_number')
            ->get()
            ->groupBy('doctor_user_id');
    }
}
