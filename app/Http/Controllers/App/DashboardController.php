<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Location;
use App\Models\Membership;
use App\Services\Reports\ReportService;
use App\Services\SetupChecklist;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant, ReportService $reports, SetupChecklist $checklist): View
    {
        $locationId = $tenant->location()?->id;
        $today = Appointment::query()->where('location_id', $locationId)->whereDate('scheduled_on', today());

        // A doctor's own day matters more to them than the whole clinic's.
        $isDoctor = $tenant->membership()?->hasRole('doctor') ?? false;

        if ($isDoctor) {
            $today->where('doctor_user_id', $request->user()->id);
        }

        $canSeeAppointments = Gate::check('appointments.view');
        $counts = $canSeeAppointments ? (clone $today)->selectRaw('status, COUNT(*) total')->groupBy('status')->toBase()->pluck('total', 'status') : collect();

        return view('app.dashboard', [
            'canSeeAppointments' => $canSeeAppointments,
            'scopeLabel' => $isDoctor ? 'Your day' : 'Today',
            'appointmentsToday' => (int) $counts->sum() - (int) $counts->get(AppointmentStatus::Cancelled->value, 0),
            'waiting' => (int) $counts->get(AppointmentStatus::CheckedIn->value, 0),
            'seen' => (int) $counts->get(AppointmentStatus::Completed->value, 0),
            'nextUp' => $canSeeAppointments
                ? (clone $today)->whereIn('status', [AppointmentStatus::CheckedIn->value, AppointmentStatus::Booked->value])->with(['patient:id,name', 'doctor:id,name'])->orderBy('token_number')->limit(8)->get()
                : collect(),
            'followUpsDue' => Gate::check('appointments.manage') ? FollowUp::query()->where('status', 'pending')->where('due_on', '<=', today()->endOfDay())->count() : null,
            'missedFollowUps' => Gate::check('appointments.manage') ? FollowUp::query()->where('status', 'missed')->count() : null,
            'collections' => Gate::check('billing.view') ? $reports->dailyCash(CarbonImmutable::today(), $locationId)['net'] : null,
            'locationCount' => Location::query()->active()->count(),
            'staffCount' => Membership::query()->active()->count(),
            'canManageStaff' => Gate::check('staff.manage'),
            'setup' => Gate::check('settings.manage') ? $checklist->for($tenant->organization()) : [],
        ]);
    }
}
