<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\Messaging\ManualMessage;
use App\Services\Messaging\Notifier;
use App\Services\Messaging\WhatsAppFactory;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppBusinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        config(['cureplus.sms.driver' => 'http', 'cureplus.sms.http.url' => null]);
    }

    private function connect(Organization $organization, bool $enabled = true): void
    {
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'whatsapp', 'is_enabled' => $enabled, 'config' => ['phone_number_id' => 'clinic-number', 'access_token' => 'clinic-token']]);
    }

    /**
     * @return array{Organization, User, Patient, Prescription}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'name' => 'Ravi', 'mobile' => '+919812345678']);
        $visit = Visit::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => today()]);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr Roy', 'issued_at' => now()]);

        return [$organization, $owner, $patient, $prescription];
    }

    private function tokenFrom(string $html): string
    {
        preg_match('/name="token" value="([^"]+)"/', $html, $match);

        return html_entity_decode($match[1]);
    }

    public function test_automatic_messages_use_the_clinics_own_business_number(): void
    {
        [$organization, , $patient] = $this->clinic();
        $this->connect($organization);

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 7, 'doctor' => 'Dr Roy']);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/clinic-number/messages')
            && $request->hasHeader('Authorization', 'Bearer clinic-token')
            && $request['to'] === '919812345678');
    }

    public function test_the_button_opens_the_devices_whatsapp_when_business_is_not_connected(): void
    {
        [, $owner, , $prescription] = $this->clinic();

        $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->assertOk()
            ->assertSee('https://wa.me/919812345678', false)
            ->assertSee("Send from your clinic's phone:")
            ->assertDontSee('WhatsApp Business</button>', false);
    }

    public function test_the_button_sends_through_business_once_connected(): void
    {
        [$organization, $owner, , $prescription] = $this->clinic();
        $this->connect($organization);
        $this->actingAs($owner, 'web');

        $page = $this->get(route('app.prescriptions.show', $prescription))->assertOk()
            ->assertDontSee('https://wa.me/', false)
            ->assertSee('WhatsApp Business');

        $this->post(route('app.messages.whatsapp'), ['token' => $this->tokenFrom((string) $page->getContent())])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/clinic-number/messages') && $request['template']['name'] === 'document_ready');
        $this->assertNotNull(AuditLog::withoutTenancy()->where('action', 'messages.sent_on_whatsapp')->first());
    }

    public function test_sms_stays_available_with_business_connected(): void
    {
        [$organization, $owner, , $prescription] = $this->clinic();
        $this->connect($organization);

        $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->assertSee('sms:+919812345678', false);
    }

    public function test_patients_never_hear_from_the_platforms_number(): void
    {
        config(['messages.whatsapp.token' => 'platform-token', 'messages.whatsapp.phone_number_id' => 'platform-number']);
        [$organization, , $patient] = $this->clinic();

        $this->assertNull(app(WhatsAppFactory::class)->forOrganization($organization->id));
        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 7, 'doctor' => 'Dr Roy']);

        Http::assertNothingSent();
    }

    public function test_owners_still_hear_from_the_platforms_number(): void
    {
        config(['messages.whatsapp.token' => 'platform-token', 'messages.whatsapp.phone_number_id' => 'platform-number']);
        [, $owner] = $this->clinic();

        app(Notifier::class)->notifyAccountOwner($owner, 'organization_restored', ['clinic' => 'City Clinic']);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/platform-number/messages'));
    }

    public function test_a_switched_off_number_counts_as_not_connected(): void
    {
        [$organization, $owner, , $prescription] = $this->clinic();
        $this->connect($organization, enabled: false);

        $this->actingAs($owner, 'web')->get(route('app.prescriptions.show', $prescription))->assertSee('https://wa.me/', false);
    }

    public function test_a_token_cannot_be_forged_used_by_another_clinic_or_kept_for_ever(): void
    {
        [$organization, $owner, $patient] = $this->clinic();
        [$other, $otherOwner] = $this->clinic();
        $this->connect($organization);
        $this->connect($other);
        $token = app(ManualMessage::class)->sendToken($organization->id, $patient->id, 'token_called', ['token' => 1, 'doctor' => 'Dr']);

        $this->actingAs($owner, 'web')->post(route('app.messages.whatsapp'), ['token' => 'made-up'])->assertForbidden();
        $this->actingAs($otherOwner, 'web')->post(route('app.messages.whatsapp'), ['token' => $token])->assertForbidden();

        $wrongEvent = Crypt::encrypt(['organization_id' => $organization->id, 'patient_id' => $patient->id, 'event' => 'organization_approved', 'variables' => [], 'expires' => now()->addHour()->timestamp]);
        $this->actingAs($owner, 'web')->post(route('app.messages.whatsapp'), ['token' => $wrongEvent])->assertForbidden();

        $this->travel(5)->hours();
        $this->post(route('app.messages.whatsapp'), ['token' => $token])->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_a_token_for_a_disconnected_number_says_so(): void
    {
        [$organization, $owner, $patient] = $this->clinic();
        $token = app(ManualMessage::class)->sendToken($organization->id, $patient->id, 'token_called', ['token' => 1, 'doctor' => 'Dr']);

        $this->actingAs($owner, 'web')->post(route('app.messages.whatsapp'), ['token' => $token])->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_the_connection_test_reports_the_result(): void
    {
        [$organization, $owner] = $this->clinic();
        $this->connect($organization);
        $this->actingAs($owner, 'web');

        $this->post(route('app.integrations.whatsapp.test'))->assertSessionHas('success');
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/clinic-number'));

        $this->get(route('app.integrations.index'))->assertOk()->assertSee('Connected')->assertSee('document_ready')->assertDontSee('clinic-token');
    }

    public function test_the_connection_test_reports_a_rejected_token(): void
    {
        Http::swap(new Factory);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'bad'], 401)]);
        [$organization, $owner] = $this->clinic();
        $this->connect($organization);
        $this->actingAs($owner, 'web');

        $this->post(route('app.integrations.whatsapp.test'))->assertSessionHas('error');
        $this->get(route('app.integrations.index'))->assertSee('Not working');
    }
}
