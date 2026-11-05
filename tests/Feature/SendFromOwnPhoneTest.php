<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\OrganizationStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingService;
use App\Services\Messaging\ManualMessage;
use App\Support\DocumentLink;
use Tests\TestCase;

class SendFromOwnPhoneTest extends TestCase
{
    /**
     * @return array{Organization, User, User, Patient, Prescription, Location}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi Kumar', 'mobile' => '+919812345678']);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today(), 'diagnosis' => 'Viral fever']);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr Roy', 'issued_at' => now()]);
        PrescriptionItem::withoutTenancy()->create(['organization_id' => $organization->id, 'prescription_id' => $prescription->id, 'medicine_name' => 'Paracetamol 500 mg', 'dosage' => '1-0-1']);

        return [$organization, $owner, $doctor, $patient, $prescription, $location];
    }

    public function test_the_addresses_open_the_right_apps(): void
    {
        $manual = app(ManualMessage::class);

        $this->assertSame('https://wa.me/919812345678?text=Hi%20Ravi%26co', $manual->whatsappUrl('+919812345678', 'Hi Ravi&co'));
        $this->assertSame('sms:+919812345678?&body=Hi%20Ravi', $manual->smsUrl('+91 98123-45678', 'Hi Ravi'));
    }

    public function test_the_message_uses_the_shared_wording_and_no_clinical_detail(): void
    {
        $text = app(ManualMessage::class)->text('document_ready', ['name' => 'Ravi', 'document' => 'prescription', 'clinic' => 'City Clinic', 'link' => 'https://x.test/a']);

        $this->assertSame('Hi Ravi, your prescription from City Clinic is ready: https://x.test/a', $text);
    }

    public function test_the_prescription_page_has_buttons_and_a_working_link_without_any_setup(): void
    {
        [, $owner, , , $prescription] = $this->clinic();

        $page = $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->assertOk();

        $page->assertSee('https://wa.me/919812345678?text=', false)
            ->assertSee('sms:+919812345678', false)
            ->assertSee('Copy link')
            ->assertSee('lg:hidden', false);
        $this->assertStringNotContainsString('Viral', $this->messageIn((string) $page->getContent()), 'The message itself must not name the diagnosis.');
    }

    private function messageIn(string $html): string
    {
        preg_match('/wa\.me\/\d+\?text=([^"]+)"/', $html, $match);

        return rawurldecode(html_entity_decode($match[1] ?? ''));
    }

    public function test_the_link_inside_the_message_really_opens_the_prescription(): void
    {
        [, $owner, , , $prescription] = $this->clinic();

        $html = $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->getContent();
        preg_match('/https?:\/\/\S+/', $this->messageIn($html), $match);
        auth('web')->logout();

        $this->get($match[0])->assertOk()->assertSee('Paracetamol 500 mg')->assertSee('Ravi Kumar');
    }

    public function test_the_patient_page_shows_the_document_with_no_sign_in(): void
    {
        [$organization, , , , $prescription] = $this->clinic();

        $page = $this->get(DocumentLink::prescription($prescription->id))->assertOk()
            ->assertSee($organization->name)
            ->assertSee('Dr Roy')
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('Download PDF')
            ->assertSee('noindex', false)
            ->assertSee('no-referrer', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->assertNotNull(AuditLog::withoutTenancy()->where('action', 'prescriptions.viewed_by_link')->first(), 'Every opening is on the record.');
    }

    public function test_the_download_on_that_page_works_and_expires_with_it(): void
    {
        [, , , , $prescription] = $this->clinic();
        config(['cureplus.document_link_hours' => 2]);

        $html = $this->get(DocumentLink::prescription($prescription->id))->getContent();
        preg_match('/href="([^"]*\/pdf\?[^"]+)"/', $html, $match);
        $pdf = html_entity_decode($match[1]);

        $this->assertStringStartsWith('%PDF', $this->get($pdf)->assertOk()->getContent());

        $this->travel(3)->hours();
        $this->get($pdf)->assertForbidden();
    }

    public function test_a_link_that_is_altered_or_expired_shows_nothing(): void
    {
        [$organization, , $doctor, $patient, $prescription, $location] = $this->clinic();
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]);
        $other = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr Other', 'issued_at' => now()]);
        $link = DocumentLink::prescription($prescription->id);

        $this->get(str_replace("/prescriptions/{$prescription->id}", "/prescriptions/{$other->id}", $link))->assertForbidden()->assertDontSee('Dr Other');
        $this->get(route('files.prescription', $prescription->id))->assertForbidden();

        $this->travel(31)->days();
        $this->get($link)->assertForbidden()->assertSee('This link has expired')->assertDontSee('Paracetamol');
    }

    public function test_the_link_lasts_thirty_days(): void
    {
        [, , , , $prescription] = $this->clinic();
        $link = DocumentLink::prescription($prescription->id);

        $this->travel(29)->days();

        $this->get($link)->assertOk();
    }

    public function test_the_bill_has_the_same_page_and_buttons(): void
    {
        [$organization, $owner, , $patient, , $location] = $this->clinic();
        $invoice = app(BillingService::class)->createInvoice($organization, $patient, [['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id]);

        $this->actingAs($owner, 'web')->get(route('app.invoices.show', $invoice))->assertOk()->assertSee('https://wa.me/919812345678?text=', false);
        auth('web')->logout();

        $this->get(DocumentLink::invoice($invoice->id))->assertOk()->assertSee('Consultation')->assertSee($invoice->number)->assertSee('₹500.00');
    }

    public function test_appointments_and_the_queue_offer_a_ready_made_reminder(): void
    {
        [$organization, $owner, $doctor, $patient, , $location] = $this->clinic();
        Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'status' => AppointmentStatus::CheckedIn, 'scheduled_on' => today()]);
        $this->actingAs($owner, 'web');

        $this->get(route('app.appointments.index'))->assertOk()->assertSee('https://wa.me/919812345678?text=', false);
        $this->get(route('app.queue.index'))->assertOk()->assertSee('https://wa.me/919812345678?text=', false);
    }

    public function test_staff_without_prescription_access_get_no_link(): void
    {
        [$organization, , , , $prescription, $location] = $this->clinic();

        $this->actingAs($this->addMember($organization, ['receptionist'], location: $location), 'web')
            ->get(route('app.prescriptions.show', $prescription))->assertForbidden();
    }

    public function test_a_practice_awaiting_approval_gets_no_link(): void
    {
        [$organization, $owner, , , $prescription] = $this->clinic();
        $organization->forceFill(['status' => OrganizationStatus::PendingVerification])->save();

        $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->assertRedirect(route('app.verification.index'));
    }
}
