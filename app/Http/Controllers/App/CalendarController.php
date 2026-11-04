<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CalendarBlock;
use App\Models\DoctorAvailability;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Services\DoctorCalendar;
use App\Services\TenantDoctors;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly TenantDoctors $doctors) {}

    public function index(): View
    {
        return view('app.calendar.index', [
            'availability' => DoctorAvailability::query()->with(['doctor:id,name', 'location:id,name'])->orderBy('weekday')->orderBy('start_time')->paginate(50),
            'blocks' => CalendarBlock::query()->where('ends_at', '>=', now())->with(['doctor:id,name', 'location:id,name'])->orderBy('starts_at')->limit(50)->get(),
            'doctors' => $this->doctors->all(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function storeAvailability(Request $request, DoctorCalendar $calendar): RedirectResponse
    {
        $data = $request->validate([
            'doctor_user_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->tenant->id())],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'between:5,120'],
        ]);

        if (! $this->doctors->isDoctor((int) $data['doctor_user_id'])) {
            throw ValidationException::withMessages(['doctor_user_id' => 'Choose a doctor from your organization.']);
        }

        // One doctor cannot be in two chambers at once, wherever they are booked.
        if ($calendar->availabilityOverlaps((int) $data['doctor_user_id'], (int) $data['weekday'], $data['start_time'], $data['end_time'])) {
            throw ValidationException::withMessages(['start_time' => 'The doctor already has hours at this time, here or at another organization.']);
        }

        DoctorAvailability::create($data);

        return back()->with('success', 'Hours added.');
    }

    public function destroyAvailability(DoctorAvailability $availability): RedirectResponse
    {
        $availability->delete();

        return back()->with('success', 'Hours removed.');
    }

    public function storeBlock(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'doctor_user_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->tenant->id())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'applies_everywhere' => ['sometimes', 'boolean'],
        ]);

        $doctorId = $data['doctor_user_id'] ?? null;

        if ($doctorId !== null && ! $this->doctors->isDoctor((int) $doctorId)) {
            throw ValidationException::withMessages(['doctor_user_id' => 'Choose a doctor from your organization.']);
        }

        // A clinic can close its own diary, but only the doctor can close the others.
        $everywhere = (bool) ($data['applies_everywhere'] ?? false) && (int) $doctorId === $request->user()->id;

        $block = CalendarBlock::create(['applies_everywhere' => $everywhere] + $data);
        $audit->record('calendar_blocks.created', $block);

        return back()->with('success', 'Time blocked.');
    }

    public function destroyBlock(CalendarBlock $block): RedirectResponse
    {
        $block->delete();

        return back()->with('success', 'Block removed.');
    }
}
