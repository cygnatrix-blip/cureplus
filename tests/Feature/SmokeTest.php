<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opens every page as every kind of person. Nothing may crash, whatever they are allowed to
 * see. New routes are picked up automatically. Lazy loading throws outside production, so
 * this also catches slow pages.
 */
class SmokeTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function pagesWithoutParameters(string $prefix): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
                && Str::startsWith((string) $route->getName(), $prefix)
                && $route->parameterNames() === [])
            ->map(fn (RoutingRoute $route) => (string) $route->getName())
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $urls
     * @param  list<int>  $allowed
     * @return list<string>
     */
    private function problems(array $urls, array $allowed): array
    {
        $problems = [];

        foreach ($urls as $url) {
            $status = $this->get($url)->getStatusCode();

            if (! in_array($status, $allowed, true)) {
                $problems[] = "{$status} {$url}";
            }
        }

        return $problems;
    }

    /**
     * @return array{Organization, User, User, Patient, Appointment, Visit, Prescription, Invoice, Location}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $account = PatientAccount::factory()->create();
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'patient_account_id' => $account->id, 'email' => $account->email]);
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::CheckedIn]);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'appointment_id' => $appointment->id, 'visited_on' => today()]);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr Smoke', 'issued_at' => now()]);
        $invoice = app(BillingService::class)->createInvoice($organization, $patient, [['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id]);

        return [$organization, $owner, $doctor, $patient, $appointment, $visit, $prescription, $invoice, $location];
    }

    public function test_public_pages_open(): void
    {
        [$organization] = $this->clinic();

        $this->assertSame([], $this->problems([
            '/', '/privacy', '/terms', '/offline', '/login', '/login/otp', '/register', '/forgot-password', '/admin/login', '/patient/login',
            '/sw.js', '/manifests/patient.webmanifest', '/'.$organization->slug, '/book/'.$organization->slug,
            route('brand.manifest', $organization->slug),
        ], [200]));
    }

    public function test_every_clinic_page_opens_for_every_role_without_crashing(): void
    {
        [$organization, $owner, $doctor, , , , , , $location] = $this->clinic();
        $pages = array_map(fn (string $name) => route($name), $this->pagesWithoutParameters('app.'));
        $this->assertGreaterThan(20, count($pages));

        $this->actingAs($owner, 'web');
        $this->assertSame([], $this->problems($pages, [200, 302, 404]), 'owner');

        $this->actingAs($doctor, 'web');
        $this->assertSame([], $this->problems($pages, [200, 302, 403, 404]), 'doctor');

        foreach (['receptionist', 'nurse', 'accountant'] as $role) {
            $this->actingAs($this->addMember($organization, [$role], location: $location), 'web');
            $this->assertSame([], $this->problems($pages, [200, 302, 403, 404]), $role);
        }
    }

    public function test_pages_for_a_single_record_open_for_those_allowed(): void
    {
        [$organization, $owner, $doctor, $patient, $appointment, $visit, $prescription, $invoice, $location] = $this->clinic();
        $membership = Membership::withoutTenancy()->where('organization_id', $organization->id)->where('user_id', $doctor->id)->firstOrFail();

        $this->actingAs($owner, 'web');
        $this->assertSame([], $this->problems([
            route('app.patients.show', $patient), route('app.patients.edit', $patient), route('app.patients.history', $patient),
            route('app.appointments.edit', $appointment), route('app.visits.show', $visit),
            route('app.prescriptions.show', $prescription), route('app.prescriptions.pdf', $prescription),
            route('app.invoices.show', $invoice), route('app.invoices.pdf', $invoice),
            route('app.locations.edit', $location), route('app.staff.edit', $membership),
        ], [200]));

        $this->actingAs($doctor, 'web');
        $this->assertSame([], $this->problems([route('app.visits.show', $visit), route('app.prescriptions.edit', $visit), route('app.prescriptions.show', $prescription)], [200]));
    }

    public function test_every_admin_page_opens_for_every_admin_role_without_crashing(): void
    {
        [$organization] = $this->clinic();
        OrganizationDocument::factory()->create(['organization_id' => $organization->id]);
        $pages = array_map(fn (string $name) => route($name), $this->pagesWithoutParameters('admin.'));
        $this->assertGreaterThan(20, count($pages));

        foreach (['super_admin', 'support', 'sales', 'finance'] as $role) {
            $this->actingAs($this->superAdmin($role), 'platform');
            $this->assertSame([], $this->problems($pages, [200, 302, 403, 404]), $role);
        }

        $this->actingAs($this->superAdmin(), 'platform');
        $this->assertSame([], $this->problems([route('admin.organizations.show', $organization), route('admin.approvals.show', $organization)], [200]));
    }

    public function test_every_patient_page_opens(): void
    {
        [, , , $patient, $appointment, , $prescription, $invoice] = $this->clinic();
        $account = PatientAccount::query()->findOrFail($patient->patient_account_id);
        $pages = array_map(fn (string $name) => route($name), $this->pagesWithoutParameters('patient.'));

        $this->actingAs($account, 'patient');
        $this->assertSame([], $this->problems($pages, [200, 302]));
        $this->assertSame([], $this->problems([
            route('patient.appointments.show', $appointment->id), route('patient.prescriptions.pdf', $prescription->id), route('patient.invoices.pdf', $invoice->id),
        ], [200]));
    }
}
