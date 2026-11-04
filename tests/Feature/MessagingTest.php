<?php

namespace Tests\Feature;

use App\Contracts\PushSender;
use App\Contracts\SmsProvider;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Jobs\SendMessage;
use App\Mail\ClinicMessageMail;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\MessageLog;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Prescription;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\Billing\BillingService;
use App\Services\Messaging\Notifier;
use App\Services\Messaging\ReminderScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\Fakes\FakePush;
use Tests\Fakes\FakeSms;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    private FakeSms $sms;

    private FakePush $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSms;
        $this->push = new FakePush;
        $this->app->instance(SmsProvider::class, $this->sms);
        $this->app->instance(PushSender::class, $this->push);
    }

    /**
     * @return array{Organization, User, Location, User, Patient}
     */
    private function clinic(array $patient = []): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Roy']), $location);

        return [$organization, $owner, $location, $doctor, Patient::factory()->create($patient + ['organization_id' => $organization->id, 'name' => 'Ravi', 'mobile' => '+919812345678'])];
    }

    private function booked(Organization $organization, Location $location, User $doctor, Patient $patient, CarbonImmutable $at): Appointment
    {
        return Appointment::factory()->create([
            'organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'patient_id' => $patient->id,
            'status' => AppointmentStatus::Booked, 'scheduled_on' => $at->toDateString(), 'starts_at' => $at, 'ends_at' => $at->addMinutes(15),
        ]);
    }

    public function test_booking_sends_a_confirmation_by_sms_when_whatsapp_is_not_set_up(): void
    {
        [$organization, , $location, $doctor, $patient] = $this->clinic();
        $monday = CarbonImmutable::parse('next monday 10:00');
        DoctorAvailability::withoutTenancy()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'weekday' => $monday->dayOfWeek, 'start_time' => '10:00', 'end_time' => '11:00', 'slot_minutes' => 15]);

        app(AppointmentService::class)->bookSlot($patient, $doctor->id, $location->id, $monday, VisitType::New);

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+919812345678', $this->sms->sent[0][0]);
        $this->assertStringContainsString('Dr Roy', $this->sms->sent[0][1]);
        $this->assertSame('sent', MessageLog::withoutTenancy()->firstOrFail()->status);
    }

    public function test_whatsapp_replaces_sms_when_the_organization_has_a_sender(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        [$organization, , , , $patient] = $this->clinic();
        OrganizationIntegration::withoutTenancy()->create(['organization_id' => $organization->id, 'kind' => 'whatsapp', 'is_enabled' => true, 'config' => ['phone_number_id' => '111', 'access_token' => 'tok']]);

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 7, 'doctor' => 'Dr Roy']);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/111/messages')
            && $request['to'] === '919812345678'
            && $request['template']['name'] === 'token_called'
            && $request['template']['components'][0]['parameters'][1]['text'] === '7');
        $this->assertCount(0, $this->sms->sent);
        $this->assertSame('wamid.1', MessageLog::withoutTenancy()->firstOrFail()->provider_id);
    }

    public function test_email_is_added_when_the_patient_has_an_address(): void
    {
        Mail::fake();
        [, , , , $patient] = $this->clinic(['email' => 'ravi@example.test']);

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 7, 'doctor' => 'Dr Roy']);

        Mail::assertSent(ClinicMessageMail::class, fn (ClinicMessageMail $mail) => $mail->hasTo('ravi@example.test'));
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_in_production_without_a_gateway_patients_still_get_email(): void
    {
        Mail::fake();
        $this->app->detectEnvironment(fn () => 'production');
        config(['cureplus.sms.driver' => 'http', 'cureplus.sms.http.url' => null]);
        [, , , , $patient] = $this->clinic(['email' => 'ravi@example.test']);

        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 7, 'doctor' => 'Dr Roy']);

        $this->assertCount(0, $this->sms->sent);
        Mail::assertSent(ClinicMessageMail::class);
        $this->assertSame(['email'], MessageLog::withoutTenancy()->pluck('channel')->all());
    }

    public function test_the_same_reminder_is_never_sent_twice(): void
    {
        [, , , , $patient] = $this->clinic();
        $notifier = app(Notifier::class);

        $notifier->notifyPatient($patient, 'appointment_reminder', ['doctor' => 'x', 'clinic' => 'y', 'time' => 'z'], 'appointment:1:reminder');
        $notifier->notifyPatient($patient, 'appointment_reminder', ['doctor' => 'x', 'clinic' => 'y', 'time' => 'z'], 'appointment:1:reminder');

        $this->assertCount(1, $this->sms->sent);
    }

    public function test_a_failed_send_is_logged_without_the_providers_message(): void
    {
        [$organization] = $this->clinic();
        $log = MessageLog::withoutTenancy()->create(['organization_id' => $organization->id, 'channel' => 'sms', 'event' => 'token_called', 'status' => 'queued']);

        (new SendMessage($log->id, 'sms', 'token_called', '+919812345678', [], $organization->id))->failed(new RuntimeException('secret token abc in the error'));

        $fresh = MessageLog::withoutTenancy()->find($log->id);
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('RuntimeException', $fresh->error);
    }

    public function test_a_message_that_cannot_be_sent_does_not_stop_a_payment(): void
    {
        $this->app->instance(SmsProvider::class, new class implements SmsProvider
        {
            public function send(string $mobile, #[\SensitiveParameter] string $message): void
            {
                throw new RuntimeException('Gateway is down');
            }
        });
        [$organization, $owner, $location, , $patient] = $this->clinic();
        $billing = app(BillingService::class);
        $invoice = $billing->createInvoice($organization, $patient, [['description' => 'x', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id]);

        $this->actingAs($owner, 'web')->post(route('app.invoices.pay', $invoice), ['mode' => 'cash', 'amount' => '500'])->assertSessionHas('success');

        $this->assertSame('paid', Invoice::withoutTenancy()->find($invoice->id)->status);
        $this->assertSame('failed', MessageLog::withoutTenancy()->latest('id')->firstOrFail()->status);
    }

    public function test_the_scheduler_reminds_the_day_before_and_the_same_day_once_each(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        [$organization, , $location, $doctor, $patient] = $this->clinic();
        $this->booked($organization, $location, $doctor, $patient, now()->toImmutable()->addHours(24));
        $scheduler = app(ReminderScheduler::class);

        $this->assertSame(1, $scheduler->run()['appointments']);
        $scheduler->run();
        $this->assertCount(1, $this->sms->sent, 'A second run sends nothing new.');

        $this->travel(23)->hours();
        // 09:00 next day, one hour before the visit.
        $scheduler->run();
        $this->assertCount(2, $this->sms->sent);
    }

    public function test_no_reminders_go_out_at_night(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-01 23:30'));
        [$organization, , $location, $doctor, $patient] = $this->clinic();
        $this->booked($organization, $location, $doctor, $patient, now()->toImmutable()->addHours(12));

        app(ReminderScheduler::class)->run();

        $this->assertCount(0, $this->sms->sent);
    }

    public function test_cancelled_appointments_get_no_reminder(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        [$organization, , $location, $doctor, $patient] = $this->clinic();
        $this->booked($organization, $location, $doctor, $patient, now()->toImmutable()->addHours(1))->update(['status' => AppointmentStatus::Cancelled]);

        app(ReminderScheduler::class)->run();

        $this->assertCount(0, $this->sms->sent);
    }

    public function test_follow_ups_are_reminded_then_land_on_the_call_list_if_missed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        [$organization, $owner, , $doctor, $patient] = $this->clinic();
        $followUp = FollowUp::withoutTenancy()->create(['organization_id' => $organization->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'due_on' => '2027-03-02', 'status' => 'pending']);

        app(ReminderScheduler::class)->run();
        $this->assertCount(1, $this->sms->sent);
        $this->assertStringContainsString('follow-up', $this->sms->sent[0][1]);

        $this->travelTo(CarbonImmutable::parse('2027-03-05 10:00'));
        $this->assertSame(1, app(ReminderScheduler::class)->run()['missed']);
        $this->assertSame('missed', FollowUp::withoutTenancy()->find($followUp->id)->status);

        $this->actingAs($owner, 'web');
        $this->get(route('app.follow-ups.index', ['view' => 'missed']))->assertOk()->assertSee($patient->name);
        $this->put(route('app.follow-ups.update', $followUp), ['status' => 'called', 'call_notes' => 'Will come Monday'])->assertRedirect();
        $this->assertSame('Will come Monday', FollowUp::withoutTenancy()->find($followUp->id)->call_notes);
    }

    public function test_a_patient_who_came_back_is_not_on_the_call_list(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        [$organization, , $location, $doctor, $patient] = $this->clinic();
        $followUp = FollowUp::withoutTenancy()->create(['organization_id' => $organization->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'due_on' => '2027-03-02', 'status' => 'pending']);
        $this->travelTo(CarbonImmutable::parse('2027-03-03 10:00'));
        Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => '2027-03-03']);

        app(ReminderScheduler::class)->run();

        $this->assertSame('done', FollowUp::withoutTenancy()->find($followUp->id)->status);
    }

    public function test_a_patient_can_mute_an_event(): void
    {
        $account = PatientAccount::factory()->create();
        [, , , , $patient] = $this->clinic(['patient_account_id' => $account->id]);
        $this->actingAs($account, 'patient');

        $this->get(route('patient.notifications'))->assertOk();
        $this->post(route('patient.notifications.save'), ['enabled' => ['token_called']])->assertRedirect();

        $notifier = app(Notifier::class);
        $notifier->notifyPatient($patient, 'appointment_reminder', ['doctor' => 'x', 'clinic' => 'y', 'time' => 'z']);
        $this->assertCount(0, $this->sms->sent);

        $notifier->notifyPatient($patient, 'token_called', ['token' => 1, 'doctor' => 'x']);
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_push_goes_to_each_device_and_expired_ones_are_removed(): void
    {
        config(['messages.push.public_key' => 'pub', 'messages.push.private_key' => 'priv']);
        $account = PatientAccount::factory()->create();
        [, , , , $patient] = $this->clinic(['patient_account_id' => $account->id]);
        $this->actingAs($account, 'patient');

        foreach (['https://push.example/a', 'https://push.example/gone'] as $endpoint) {
            $this->postJson(route('patient.push.subscribe'), ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
        }

        $this->push->expired = ['https://push.example/gone'];
        app(Notifier::class)->notifyPatient($patient, 'token_called', ['token' => 7, 'doctor' => 'Dr Roy']);

        $this->assertCount(2, $this->push->sent);
        $this->assertSame('It is your turn', $this->push->sent[0]['title']);
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_push_keys_are_encrypted_and_only_the_owner_can_unsubscribe(): void
    {
        $account = PatientAccount::factory()->create();
        $other = PatientAccount::factory()->create();
        $endpoint = 'https://push.example/mine';

        $this->actingAs($account, 'patient')->postJson(route('patient.push.subscribe'), ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret']])->assertOk();
        $this->assertStringNotContainsString('auth-secret', (string) DB::table('push_subscriptions')->value('auth_token'));

        $this->actingAs($other, 'patient')->postJson(route('patient.push.unsubscribe'), ['endpoint' => $endpoint])->assertOk();
        $this->assertSame(1, PushSubscription::query()->count());

        $this->actingAs($account, 'patient')->postJson(route('patient.push.unsubscribe'), ['endpoint' => $endpoint])->assertOk();
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_a_subscription_must_be_a_real_https_endpoint(): void
    {
        $account = PatientAccount::factory()->create();

        $this->actingAs($account, 'patient')->postJson(route('patient.push.subscribe'), ['endpoint' => 'http://evil.test', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertJsonValidationErrors('endpoint');
    }

    public function test_staff_can_subscribe_too(): void
    {
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->postJson(route('app.push.subscribe'), ['endpoint' => 'https://push.example/staff', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();

        $this->assertSame('user', PushSubscription::query()->firstOrFail()->subscriber_type);
    }

    public function test_calling_a_token_and_taking_payment_tell_the_patient(): void
    {
        [$organization, $owner, $location, $doctor, $patient] = $this->clinic();
        $appointment = Appointment::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'patient_id' => $patient->id, 'token_number' => 42]);

        $this->actingAs($owner, 'web')->post(route('app.visits.start', $appointment))->assertRedirect();
        $this->assertStringContainsString('token 42', $this->sms->sent[0][1]);

        $billing = app(BillingService::class);
        $invoice = $billing->createInvoice($organization, $patient, [['description' => 'x', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0]], ['location_id' => $location->id]);
        $billing->recordPayment($invoice, 50000, 'cash');

        $this->assertStringContainsString('₹500.00', end($this->sms->sent)[1]);
    }

    public function test_a_prescription_link_works_without_login_only_while_signed_and_unexpired(): void
    {
        [$organization, $owner, $location, $doctor, $patient] = $this->clinic();
        $visit = Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id]);
        $prescription = Prescription::withoutTenancy()->create(['organization_id' => $organization->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id, 'location_id' => $location->id, 'doctor_user_id' => $doctor->id, 'doctor_name' => 'Dr Roy', 'issued_at' => now()]);

        $this->actingAs($owner, 'web')->post(route('app.prescriptions.share', $prescription))->assertSessionHas('success');
        $this->assertStringContainsString('/files/prescriptions/', end($this->sms->sent)[1]);

        auth('web')->logout();
        $this->get(route('files.prescription', $prescription->id))->assertForbidden();

        $signed = URL::temporarySignedRoute('files.prescription', now()->addHour(), ['prescription' => $prescription->id]);
        $this->assertStringStartsWith('%PDF', $this->get($signed)->assertOk()->getContent());

        $this->travel(2)->hours();
        $this->get($signed)->assertForbidden();
    }

    public function test_the_reminder_task_is_scheduled_before_the_queue_worker(): void
    {
        $names = collect(app(Schedule::class)->events())->pluck('description')->all();

        $this->assertContains('reminders', $names);
        $this->assertLessThan(array_search('queue-worker', $names, true), array_search('reminders', $names, true));
    }

    public function test_the_service_worker_shows_pushes_and_opens_the_right_page(): void
    {
        $script = $this->get('/sw.js')->assertOk()->getContent();

        $this->assertStringContainsString("addEventListener('push'", $script);
        $this->assertStringContainsString("addEventListener('notificationclick'", $script);
    }

    public function test_other_tenants_follow_ups_are_404(): void
    {
        [$organization, , , $doctor, $patient] = $this->clinic();
        [, $stranger] = $this->organizationWithOwner();
        $followUp = FollowUp::withoutTenancy()->create(['organization_id' => $organization->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'due_on' => today()]);

        $this->actingAs($stranger, 'web')->put(route('app.follow-ups.update', $followUp->id), ['status' => 'done'])->assertNotFound();
    }
}
