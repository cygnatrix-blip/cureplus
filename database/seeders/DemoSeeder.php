<?php

namespace Database\Seeders;

use App\Actions\RegisterPatient;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\VisitType;
use App\Models\DoctorAvailability;
use App\Models\DoctorFee;
use App\Models\DoctorProfile;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\ServiceItem;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\Billing\BillingService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data with well-known passwords, so it refuses to run in production.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'Demo@12345';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        PlatformAdmin::query()->firstOrCreate(['email' => 'admin@cureplus.test'], [
            'name' => 'Platform Admin', 'password' => self::PASSWORD, 'role' => 'super_admin',
        ]);

        $owner = $this->user('Asha Sen', 'owner@cityclinic.test', '+919800000001');
        $doctor = $this->user('Dr. Rohan Roy', 'roy@cureplus.test', '+919800000002');
        $receptionist = $this->user('Mita Das', 'reception@cityclinic.test', '+919800000003');

        $clinic = $this->organization('City Clinic', 'city-clinic', OrganizationType::Clinic);
        $clinic->forceFill([
            'brand_color' => '#1d4ed8',
            'branding' => [
                'accent_color' => '#0ea5e9',
                'corners' => 'soft',
                'tagline' => 'Trusted family care in the heart of Kolkata',
                'about' => 'Experienced doctors, short waits and a team that remembers your name.',
                'highlights' => ['Same-day appointments', 'Open six days a week', 'Records online'],
                'contact_phone' => '033 4000 1234',
                'contact_email' => 'care@cityclinic.test',
                'show_fees' => true,
                'established_year' => 1998,
                'services' => [
                    ['title' => 'General medicine', 'text' => 'Fever, infections, blood pressure, diabetes and everyday illness.'],
                    ['title' => 'Child care', 'text' => 'Check-ups, growth monitoring and vaccinations.'],
                    ['title' => 'Health check-ups', 'text' => 'Yearly check-ups and advice on staying well.'],
                ],
                'faqs' => [
                    ['question' => 'Do I need an appointment?', 'answer' => 'Booking online saves you waiting, but we also see walk-in patients when we can.'],
                    ['question' => 'How do I get my prescription?', 'answer' => 'You can download it from your account after your visit.'],
                ],
            ],
        ])->save();
        $branches = [
            Location::withoutTenancy()->firstOrCreate(['organization_id' => $clinic->id, 'name' => 'Park Street'], ['city' => 'Kolkata', 'is_active' => true]),
            Location::withoutTenancy()->firstOrCreate(['organization_id' => $clinic->id, 'name' => 'Salt Lake'], ['city' => 'Kolkata', 'is_active' => true]),
        ];
        $this->member($clinic, $owner, ['owner'], $branches);
        $this->member($clinic, $doctor, ['doctor'], $branches, 'dr-rohan-roy');
        $this->member($clinic, $receptionist, ['receptionist'], [$branches[0]]);

        // Same doctor, second organization: this is what the organization switcher is for.
        $solo = $this->organization('Dr. Rohan Roy', 'dr-rohan-roy', OrganizationType::SoloPractice);
        $chamber = Location::withoutTenancy()->firstOrCreate(['organization_id' => $solo->id, 'name' => 'Home chamber'], ['city' => 'Howrah', 'is_active' => true]);
        $this->member($solo, $doctor, ['owner', 'doctor'], [$chamber], 'dr-rohan-roy');

        $this->clinicalData($clinic, $branches[0], $doctor);
    }

    /**
     * A working day at City Clinic, so each screen has something to show.
     */
    private function clinicalData(Organization $clinic, Location $location, User $doctor): void
    {
        DoctorProfile::query()->firstOrCreate(['user_id' => $doctor->id], ['qualification' => 'MBBS, MD', 'specialty' => 'General Physician', 'offers_video' => true, 'video_link' => 'https://meet.google.com/abc-defg-hij', 'registration_number' => 'WB-12345', 'registration_council' => 'West Bengal Medical Council'])->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            DoctorAvailability::withoutTenancy()->firstOrCreate(
                ['organization_id' => $clinic->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'weekday' => $weekday],
                ['start_time' => '10:00', 'end_time' => '14:00', 'slot_minutes' => 15],
            );
        }

        foreach (['new' => 50000, 'follow_up' => 30000, 'emergency' => 80000, 'procedure' => 100000] as $type => $amount) {
            DoctorFee::withoutTenancy()->firstOrCreate(['organization_id' => $clinic->id, 'doctor_user_id' => null, 'visit_type' => $type], ['amount_paise' => $amount]);
        }

        ServiceItem::withoutTenancy()->firstOrCreate(['organization_id' => $clinic->id, 'name' => 'Cosmetic peel'], ['price_paise' => 150000, 'is_taxable' => true, 'gst_rate_bp' => 1800, 'sac_hsn_code' => '999722']);
        ServiceItem::withoutTenancy()->firstOrCreate(['organization_id' => $clinic->id, 'name' => 'ECG'], ['price_paise' => 30000]);

        if (Patient::withoutTenancy()->where('organization_id', $clinic->id)->exists()) {
            return;
        }

        $register = app(RegisterPatient::class);
        $appointments = app(AppointmentService::class);

        foreach ([['Ravi Kumar', '+919811111111', 42, 'male', 'ravi@example.test'], ['Sita Devi', '+919822222222', 35, 'female', 'sita@example.test'], ['Anil Ghosh', '+919833333333', 61, 'male', null]] as $index => [$name, $mobile, $age, $gender, $email]) {
            $patient = $register($clinic->id, ['name' => $name, 'mobile' => $mobile, 'email' => $email, 'age' => $age, 'gender' => $gender]);
            $appointment = $appointments->walkIn($patient, $doctor->id, $location->id, VisitType::New);

            if ($index === 0) {
                Visit::withoutTenancy()->create([
                    'organization_id' => $clinic->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
                    'appointment_id' => $appointment->id, 'visited_on' => today()->toDateString(), 'status' => 'open',
                    'complaints' => 'Fever for 3 days', 'diagnosis' => 'Acute febrile illness',
                ]);
                $billing = app(BillingService::class);
                $invoice = $billing->createInvoice($clinic, $patient, [['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'appointment_id' => $appointment->id]);
                $billing->recordPayment($invoice, 50000, 'cash');
            }
        }
    }

    private function user(string $name, string $email, string $mobile): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name, 'mobile' => $mobile, 'password' => self::PASSWORD, 'terms_accepted_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->saveQuietly();

        return $user;
    }

    private function organization(string $name, string $slug, OrganizationType $type): Organization
    {
        $organization = Organization::query()->firstOrNew(['slug' => $slug]);
        $organization->fill(['name' => $name, 'type' => $type]);
        $organization->forceFill(['status' => OrganizationStatus::Approved, 'approved_at' => now()])->save();

        return $organization;
    }

    /**
     * @param  list<string>  $roles
     * @param  list<Location>  $locations
     */
    private function member(Organization $organization, User $user, array $roles, array $locations, ?string $doctorSlug = null): void
    {
        $membership = Membership::withoutTenancy()->firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['status' => 'active', 'doctor_slug' => $doctorSlug, 'joined_at' => now()],
        );
        $membership->roles()->sync(Role::query()->whereIn('key', $roles)->pluck('id'));
        $membership->locations()->sync(collect($locations)->pluck('id'));
    }
}
