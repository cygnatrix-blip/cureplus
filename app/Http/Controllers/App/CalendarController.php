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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly TenantDoctors $doctors) {}

    public function index(): View
    {
        return view('app.calendar.index', [
            // A practice has a few rows per doctor per day, so the whole week fits on one page.
            'availability' => DoctorAvailability::query()->with(['doctor:id,name', 'location:id,name'])->orderBy('doctor_user_id')->orderBy('weekday')->orderBy('start_time')->limit(500)->get()->groupBy('doctor_user_id'),
            'blocks' => CalendarBlock::query()->where('ends_at', '>=', now())->with(['doctor:id,name', 'location:id,name'])->orderBy('starts_at')->limit(50)->get(),
            'doctors' => $this->doctors->all(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
        ]);
    }

    /**
     * The same hours on every day picked, so a Monday-to-Saturday doctor is set up in one go.
     * All days are saved or none are, so a clash on one day never leaves a half-done week.
     */
    public function storeAvailability(Request $request, DoctorCalendar $calendar, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'doctor_user_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->tenant->id())],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'distinct', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'between:5,120'],
        ], ['weekdays.required' => 'Pick at least one day.']);

        $doctorId = $this->doctorOrFail((int) $data['doctor_user_id']);
        $days = array_map('intval', $data['weekdays']);
        $this->assertNoClash($calendar, $doctorId, $days, [[$data['start_time'], $data['end_time']]]);

        DB::transaction(function () use ($data, $days): void {
            foreach ($days as $day) {
                DoctorAvailability::create(['weekday' => $day] + Arr::except($data, ['weekdays']));
            }
        });

        $audit->record('doctor_availability.added', $this->tenant->organization(), ['doctor_user_id' => $doctorId, 'weekdays' => $days]);

        return back()->with('success', count($days) === 1 ? 'Hours added.' : 'Hours added to '.count($days).' days.');
    }

    public function editAvailability(DoctorAvailability $availability): View
    {
        return view('app.calendar.edit', [
            'availability' => $availability->load('doctor:id,name'),
            'locations' => Location::query()->active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Bookings already made keep their time; only slots not yet booked follow the new hours.
     */
    public function updateAvailability(Request $request, DoctorAvailability $availability, DoctorCalendar $calendar, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->tenant->id())],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'between:5,120'],
        ]);

        $this->assertNoClash($calendar, (int) $availability->doctor_user_id, [(int) $availability->weekday], [[$data['start_time'], $data['end_time']]], [$availability->id]);

        $availability->update($data);
        $audit->record('doctor_availability.updated', $availability, ['doctor_user_id' => $availability->doctor_user_id, 'fields' => array_keys($availability->getChanges())]);

        return redirect()->route('app.calendar.index')->with('success', 'Hours updated.');
    }

    /**
     * Copies every session a doctor has here on one day, morning and evening alike, to other days.
     * With "replace", what those days already have here is swapped for the copy; otherwise any
     * clash stops the copy. Hours at other organizations are never removed, only checked.
     */
    public function copyDay(Request $request, DoctorCalendar $calendar, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'doctor_user_id' => ['required', 'integer'],
            'from_weekday' => ['required', 'integer', 'between:0,6'],
            'to_weekdays' => ['required', 'array', 'min:1'],
            'to_weekdays.*' => ['integer', 'distinct', 'between:0,6', 'different:from_weekday'],
            'replace' => ['sometimes', 'boolean'],
        ], ['to_weekdays.required' => 'Pick at least one day to copy to.', 'to_weekdays.*.different' => 'Pick days other than the one you are copying.']);

        $doctorId = $this->doctorOrFail((int) $data['doctor_user_id']);
        $targets = array_map('intval', $data['to_weekdays']);
        $sources = DoctorAvailability::query()->where('doctor_user_id', $doctorId)->where('weekday', (int) $data['from_weekday'])->where('is_active', true)->orderBy('start_time')->get();

        if ($sources->isEmpty()) {
            throw ValidationException::withMessages(['from_weekday' => 'This doctor has no hours here on '.DoctorAvailability::DAYS[(int) $data['from_weekday']].' to copy.']);
        }

        $replace = (bool) ($data['replace'] ?? false);
        $replaced = $replace
            ? DoctorAvailability::query()->where('doctor_user_id', $doctorId)->whereIn('weekday', $targets)->pluck('id')->all()
            : [];
        $sessions = $sources->map(fn (DoctorAvailability $row) => [substr($row->start_time, 0, 5), substr($row->end_time, 0, 5)])->all();
        $this->assertNoClash($calendar, $doctorId, $targets, $sessions, $replaced, 'to_weekdays');

        DB::transaction(function () use ($sources, $targets, $replaced): void {
            DoctorAvailability::query()->whereIn('id', $replaced)->delete();

            foreach ($targets as $day) {
                foreach ($sources as $row) {
                    DoctorAvailability::create(['weekday' => $day] + $row->only(['doctor_user_id', 'location_id', 'start_time', 'end_time', 'slot_minutes']));
                }
            }
        });

        $audit->record('doctor_availability.copied', $this->tenant->organization(), ['doctor_user_id' => $doctorId, 'from' => (int) $data['from_weekday'], 'to' => $targets, 'replaced' => count($replaced)]);

        return back()->with('success', DoctorAvailability::DAYS[(int) $data['from_weekday']].' copied to '.implode(', ', array_map(fn (int $day) => DoctorAvailability::DAYS[$day], $targets)).'.');
    }

    public function destroyAvailability(DoctorAvailability $availability, AuditLogger $audit): RedirectResponse
    {
        $availability->delete();
        $audit->record('doctor_availability.removed', $availability, ['doctor_user_id' => $availability->doctor_user_id]);

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

    private function doctorOrFail(int $doctorId): int
    {
        if (! $this->doctors->isDoctor($doctorId)) {
            throw ValidationException::withMessages(['doctor_user_id' => 'Choose a doctor from your organization.']);
        }

        return $doctorId;
    }

    /**
     * One doctor cannot be in two chambers at once, wherever they are booked, so every
     * day is checked against hours here and at other organizations, and each clash is named.
     *
     * @param  list<int>  $days
     * @param  list<array{string, string}>  $sessions
     * @param  list<int>  $ignoreIds
     */
    private function assertNoClash(DoctorCalendar $calendar, int $doctorId, array $days, array $sessions, array $ignoreIds = [], string $field = 'start_time'): void
    {
        $clashes = [];

        foreach ($days as $day) {
            foreach ($sessions as [$start, $end]) {
                if ($calendar->availabilityOverlaps($doctorId, $day, $start, $end, $ignoreIds)) {
                    $clashes[] = DoctorAvailability::DAYS[$day]." {$start}–{$end}";
                }
            }
        }

        if ($clashes !== []) {
            throw ValidationException::withMessages([$field => 'The doctor already has hours at this time, here or at another organization: '.implode(', ', $clashes).'.']);
        }
    }

    public function destroyBlock(CalendarBlock $block, AuditLogger $audit): RedirectResponse
    {
        $block->delete();
        $audit->record('calendar_blocks.removed', $block, ['doctor_user_id' => $block->doctor_user_id]);

        return back()->with('success', 'Block removed.');
    }
}
