<?php

namespace App\Http\Controllers;

use App\Actions\RegisterPatient;
use App\Enums\OtpPurpose;
use App\Enums\VisitType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Consent;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Rules\IndianMobile;
use App\Rules\PassesCaptcha;
use App\Services\AppointmentService;
use App\Services\AuditLogger;
use App\Services\Auth\OtpService;
use App\Services\DoctorCalendar;
use App\Services\Portal\PatientRecords;
use App\Services\Portal\PortalBrand;
use App\Services\Portal\PublicBooking;
use App\Support\Mobile;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    private const DRAFT = 'booking.draft';

    private const DAYS_SHOWN = 14;

    public function __construct(private readonly PublicBooking $booking, private readonly DoctorCalendar $calendar) {}

    public function show(Request $request, string $slug, ?string $doctorSlug = null): View
    {
        $organization = $this->booking->organization($slug);
        app(PortalBrand::class)->remember($organization);

        $preselected = $doctorSlug ? $this->booking->doctorBySlug($organization, $doctorSlug) : null;
        $locations = $this->booking->locations($organization);
        $locationId = $request->integer('location') ?: ($locations->count() === 1 ? $locations->first()->id : null);
        $doctors = $this->booking->doctorCards($organization, $locationId);
        $doctorId = $preselected->user_id ?? ($request->integer('doctor') ?: ($doctors->count() === 1 ? $doctors->first()['id'] : null));

        $canShowSlots = $locationId && $doctorId && $this->booking->isBookableDoctor($organization, (int) $doctorId, (int) $locationId);
        $date = $this->chosenDate($request, $canShowSlots ? (int) $doctorId : null, $organization->id, (int) $locationId);

        return view('booking.show', [
            'organization' => $organization,
            'locations' => $locations,
            'doctors' => $doctors,
            'preselected' => $preselected,
            'locationId' => $locationId,
            'doctorId' => $doctorId,
            // Looked up across all locations, so a doctor's own link always names the doctor.
            'selectedDoctor' => $this->booking->doctorCards($organization)->firstWhere('id', (int) $doctorId),
            'date' => $date,
            'days' => collect(range(0, self::DAYS_SHOWN - 1))->map(fn (int $offset) => CarbonImmutable::today()->addDays($offset)),
            'slots' => $canShowSlots ? $this->calendar->slots((int) $doctorId, $organization->id, (int) $locationId, $date) : [],
            'canShowSlots' => $canShowSlots,
        ]);
    }

    public function choose(Request $request, string $slug): RedirectResponse
    {
        $organization = $this->booking->organization($slug);
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'doctor_user_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'visit_type' => ['nullable', 'in:new,follow_up'],
        ]);

        abort_unless($this->booking->isBookableDoctor($organization, (int) $data['doctor_user_id'], (int) $data['location_id']), 404);

        $request->session()->put(self::DRAFT, [
            'organization_id' => $organization->id,
            'location_id' => (int) $data['location_id'],
            'doctor_user_id' => (int) $data['doctor_user_id'],
            'starts_at' => $data['starts_at'],
            'visit_type' => $data['visit_type'] ?? 'new',
        ]);

        return redirect()->route('book.details', $slug);
    }

    public function details(Request $request, string $slug, PatientRecords $records): View|RedirectResponse
    {
        $organization = $this->booking->organization($slug);
        $draft = $this->draft($request, $organization);

        if ($draft === null) {
            return redirect()->route('booking.show', $slug);
        }

        /** @var PatientAccount|null $account */
        $account = Auth::guard('patient')->user();

        return view('booking.details', [
            'organization' => $organization,
            'draft' => $draft,
            'startsAt' => CarbonImmutable::createFromFormat('Y-m-d H:i', $draft['starts_at']),
            'doctor' => $this->booking->doctorCards($organization, $draft['location_id'])->firstWhere('id', $draft['doctor_user_id']),
            'location' => $this->booking->locations($organization)->firstWhere('id', $draft['location_id']),
            'account' => $account,
            'family' => $account ? $records->patients($account)->where('organization_id', $organization->id)->values() : collect(),
            'pending' => $request->session()->get('booking.pending'),
        ]);
    }

    /**
     * Step one of a guest booking: nothing is saved until the email is proved by a code.
     */
    public function sendOtp(Request $request, string $slug, OtpService $otp): RedirectResponse
    {
        $organization = $this->booking->organization($slug);

        if ($this->draft($request, $organization) === null) {
            return redirect()->route('booking.show', $slug);
        }

        $request->merge([
            'mobile' => Mobile::normalize((string) $request->input('mobile')) ?? $request->input('mobile'),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'mobile' => ['required', new IndianMobile],
            'age' => ['nullable', 'integer', 'between:0,130'],
            'gender' => ['nullable', 'in:male,female,other'],
            'consent' => ['accepted'],
            'cf-turnstile-response' => [new PassesCaptcha],
        ]);

        $otp->sendToEmail(OtpPurpose::PatientLogin, $data['email']);
        $request->session()->put('booking.pending', Arr::only($data, ['name', 'email', 'mobile', 'age', 'gender']));

        return redirect()->route('book.details', $slug)->with('status', __('We sent a code to your email.'));
    }

    public function confirm(Request $request, string $slug, OtpService $otp, AppointmentService $appointments, RegisterPatient $register, PatientRecords $records, AuditLogger $audit): RedirectResponse
    {
        $organization = $this->booking->organization($slug);
        $draft = $this->draft($request, $organization);

        if ($draft === null) {
            return redirect()->route('booking.show', $slug);
        }

        /** @var PatientAccount|null $account */
        $account = Auth::guard('patient')->user();

        $patient = $account
            ? $this->patientForAccount($request, $account, $organization, $records, $register)
            : $this->patientFromOtp($request, $organization, $otp, $register, $records);

        try {
            $appointment = $appointments->bookSlot(
                $patient,
                $draft['doctor_user_id'],
                $draft['location_id'],
                CarbonImmutable::createFromFormat('Y-m-d H:i', $draft['starts_at'])->startOfMinute(),
                VisitType::from($draft['visit_type']),
                'link',
            );
        } catch (SlotUnavailableException) {
            $request->session()->forget(self::DRAFT);

            return redirect()->route('booking.show', $slug)->with('error', __('That slot has just been taken. Please choose another.'));
        }

        $request->session()->forget([self::DRAFT, 'booking.pending']);
        $audit->record('appointments.booked_online', $appointment);

        return redirect()->route('patient.appointments.show', $appointment->id)->with('booked', true);
    }

    private function patientFromOtp(Request $request, Organization $organization, OtpService $otp, RegisterPatient $register, PatientRecords $records): Patient
    {
        $pending = $request->session()->get('booking.pending');
        $data = $request->validate(['code' => ['required', 'digits:'.config('cureplus.otp.length')]]);

        if (! is_array($pending) || ! $otp->verify(OtpPurpose::PatientLogin, $pending['email'], $data['code'])) {
            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        $account = PatientAccount::query()->firstOrCreate(['email' => $pending['email']], ['name' => $pending['name'], 'mobile' => $pending['mobile'], 'locale' => app()->getLocale()]);

        abort_unless($account->isActive(), 403);

        $account->forceFill(['email_verified_at' => now()])->save();
        Auth::guard('patient')->login($account);
        $request->session()->regenerate();
        $this->recordConsent($account, $organization);

        return $this->findOrRegister($organization, $pending, $register, $records, $account);
    }

    private function patientForAccount(Request $request, PatientAccount $account, Organization $organization, PatientRecords $records, RegisterPatient $register): Patient
    {
        if ($request->filled('patient_id')) {
            // Only someone already linked to this account, at this organization, can be chosen.
            return $records->patients($account)->where('organization_id', $organization->id)->firstWhere('id', $request->integer('patient_id')) ?? abort(404);
        }

        $request->merge(['mobile' => Mobile::normalize((string) $request->input('mobile', $account->mobile)) ?? $request->input('mobile')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', new IndianMobile],
            'age' => ['nullable', 'integer', 'between:0,130'],
            'gender' => ['nullable', 'in:male,female,other'],
            'relation' => ['nullable', 'string', 'max:40'],
            'consent' => ['accepted'],
        ]);

        if ($account->mobile === null) {
            $account->forceFill(['mobile' => $data['mobile']])->save();
        }

        $this->recordConsent($account, $organization);

        return $this->findOrRegister($organization, Arr::except($data, ['consent']) + ['email' => $account->email], $register, $records, $account);
    }

    /**
     * Matches on the proved email and the name. Matching a clinic's existing record by
     * mobile instead would let anyone who knows a patient's name and number attach their
     * own email to that record and read it, so a mismatch makes a new record for the
     * clinic to review.
     *
     * @param  array{name: string, email: string, mobile: string, age?: int|string|null, gender?: string|null, relation?: string|null}  $details
     */
    private function findOrRegister(Organization $organization, array $details, RegisterPatient $register, PatientRecords $records, PatientAccount $account): Patient
    {
        $patient = Patient::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->where('email', $details['email'])
            ->where('name', $details['name'])
            ->first()
            ?? $register($organization->id, [
                'name' => $details['name'],
                'email' => $details['email'],
                'mobile' => $details['mobile'],
                'age' => isset($details['age']) && $details['age'] !== '' ? (int) $details['age'] : null,
                'gender' => $details['gender'] ?? null,
                'relation' => $details['relation'] ?? null,
            ]);

        $records->link($account);

        return $patient->refresh();
    }

    /**
     * The DPDP record of what they agreed to, and with whom.
     */
    private function recordConsent(PatientAccount $account, Organization $organization): void
    {
        Consent::query()->firstOrCreate(
            ['patient_account_id' => $account->id, 'purpose' => Consent::BOOKING_AND_CARE, 'grantee_organization_id' => $organization->id, 'revoked_at' => null],
            ['granted_via' => 'booking', 'granted_at' => now()],
        );
    }

    /**
     * @return array{organization_id: int, location_id: int, doctor_user_id: int, starts_at: string, visit_type: string}|null
     */
    private function draft(Request $request, Organization $organization): ?array
    {
        $draft = $request->session()->get(self::DRAFT);

        return is_array($draft) && $draft['organization_id'] === $organization->id ? $draft : null;
    }

    /**
     * With no date asked for, it opens on the doctor's first day with a free slot,
     * so a patient never lands on an empty page and has to hunt.
     */
    private function chosenDate(Request $request, ?int $doctorId, int $organizationId, int $locationId): CarbonImmutable
    {
        $value = $request->query('date');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        $today = CarbonImmutable::today();

        if ($doctorId !== null) {
            for ($offset = 0; $offset < self::DAYS_SHOWN; $offset++) {
                $slots = $this->calendar->slots($doctorId, $organizationId, $locationId, $today->addDays($offset));

                if (collect($slots)->contains('available', true)) {
                    return $today->addDays($offset);
                }
            }
        }

        return $today;
    }
}
