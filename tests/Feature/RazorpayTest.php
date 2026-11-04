<?php

namespace Tests\Feature;

use App\Jobs\SendMessage;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AuditLogger;
use App\Services\Billing\BillingService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RazorpayTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_value';

    private const KEY_SECRET = 'rzp_secret_value_1234';

    /**
     * @return array{Organization, User, Invoice}
     */
    private function setUpClinic(bool $enabled = true): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        OrganizationIntegration::withoutTenancy()->create([
            'organization_id' => $organization->id, 'kind' => 'razorpay', 'is_enabled' => $enabled,
            'config' => ['key_id' => 'rzp_test_abc', 'key_secret' => self::KEY_SECRET, 'webhook_secret' => self::WEBHOOK_SECRET, 'mode' => 'test'],
        ]);

        return [$organization, $owner, $this->invoice($organization, $location)];
    }

    private function invoice(Organization $organization, Location $location): Invoice
    {
        $patient = Patient::factory()->create(['organization_id' => $organization->id]);

        return app(BillingService::class)->createInvoice($organization, $patient, [
            ['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0],
        ], ['location_id' => $location->id]);
    }

    private function pendingLink(Invoice $invoice, string $linkId = 'plink_1'): Payment
    {
        return Payment::withoutTenancy()->create([
            'organization_id' => $invoice->organization_id, 'invoice_id' => $invoice->id, 'amount_paise' => 50000,
            'mode' => 'razorpay', 'status' => 'pending', 'gateway_link_id' => $linkId, 'gateway_link_url' => 'https://rzp.io/i/x',
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function webhook(Organization $organization, array $body, string $eventId = 'evt_1', ?string $signature = null): TestResponse
    {
        $payload = json_encode($body);

        return $this->call('POST', route('webhooks.razorpay', $organization->uuid), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $payload, self::WEBHOOK_SECRET),
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function paidEvent(string $linkId = 'plink_1', string $paymentId = 'pay_1'): array
    {
        return ['event' => 'payment_link.paid', 'payload' => ['payment_link' => ['entity' => ['id' => $linkId]], 'payment' => ['entity' => ['id' => $paymentId]]]];
    }

    public function test_a_signed_webhook_marks_the_bill_paid(): void
    {
        [$organization, , $invoice] = $this->setUpClinic();
        $this->pendingLink($invoice);

        $this->webhook($organization, $this->paidEvent())->assertOk();

        $fresh = Invoice::withoutTenancy()->find($invoice->id);
        $this->assertSame('paid', $fresh->status);
        $this->assertSame(50000, $fresh->paid_paise);
        $this->assertSame('pay_1', Payment::withoutTenancy()->firstOrFail()->gateway_payment_id);
    }

    public function test_a_retried_webhook_credits_only_once(): void
    {
        [$organization, , $invoice] = $this->setUpClinic();
        $this->pendingLink($invoice);

        $this->webhook($organization, $this->paidEvent())->assertOk();
        $this->webhook($organization, $this->paidEvent())->assertOk();
        // Same payment under a new event id must also change nothing.
        $this->webhook($organization, $this->paidEvent(), 'evt_2')->assertOk();

        $this->assertSame(50000, Invoice::withoutTenancy()->find($invoice->id)->paid_paise);
    }

    public function test_a_forged_webhook_changes_nothing_and_is_logged(): void
    {
        [$organization, , $invoice] = $this->setUpClinic();
        $this->pendingLink($invoice);

        $this->webhook($organization, $this->paidEvent(), 'evt_1', 'forged-signature')->assertStatus(401);

        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoice->id)->status);
        $this->assertSame(0, WebhookEvent::withoutTenancy()->count());
        $this->assertSame(1, SecurityEvent::query()->where('type', 'webhook_rejected')->count());
    }

    public function test_one_organizations_secret_cannot_sign_for_another(): void
    {
        [$a] = $this->setUpClinic();
        [$b, , $invoiceB] = $this->setUpClinic();
        OrganizationIntegration::withoutTenancy()->where('organization_id', $b->id)->firstOrFail()
            ->forceFill(['config' => ['key_id' => 'k', 'key_secret' => 's', 'webhook_secret' => 'other-secret']])->save();
        $this->pendingLink($invoiceB);

        // Signed with A's secret, sent to B's URL.
        $this->webhook($b, $this->paidEvent())->assertStatus(401);
        // A's URL, but the link belongs to B: nothing to credit.
        $this->webhook($a, $this->paidEvent())->assertOk();

        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoiceB->id)->status);
    }

    public function test_an_unknown_organization_is_404_and_webhooks_need_no_csrf_token(): void
    {
        $this->post(route('webhooks.razorpay', 'no-such-uuid'), [])->assertNotFound();
    }

    public function test_the_payment_callback_verifies_its_signature(): void
    {
        [$organization, , $invoice] = $this->setUpClinic();
        $this->pendingLink($invoice);
        $query = [
            'razorpay_payment_link_id' => 'plink_1',
            'razorpay_payment_link_reference_id' => 'inv-1',
            'razorpay_payment_link_status' => 'paid',
            'razorpay_payment_id' => 'pay_9',
        ];

        $this->get(route('pay.callback', [$organization->uuid] + $query + ['razorpay_signature' => 'forged']))->assertOk()->assertSee('could not confirm');
        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoice->id)->status);

        $signature = hash_hmac('sha256', 'plink_1|inv-1|paid|pay_9', self::KEY_SECRET);
        $this->get(route('pay.callback', [$organization->uuid] + $query + ['razorpay_signature' => $signature]))->assertOk()->assertSee('Thank you');
        $this->assertSame('paid', Invoice::withoutTenancy()->find($invoice->id)->status);

        // Then the webhook arrives too.
        $this->webhook($organization, $this->paidEvent('plink_1', 'pay_9'))->assertOk();
        $this->assertSame(50000, Invoice::withoutTenancy()->find($invoice->id)->paid_paise);
    }

    public function test_a_payment_link_uses_the_due_amount_and_goes_to_the_patient(): void
    {
        Bus::fake();
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_9', 'short_url' => 'https://rzp.io/i/abc'])]);
        [, $owner, $invoice] = $this->setUpClinic();
        app(BillingService::class)->recordPayment($invoice, 20000, 'cash');

        $this->actingAs($owner, 'web')->post(route('app.invoices.payment-link', $invoice))->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => $request['amount'] === 30000 && $request['currency'] === 'INR' && $request->hasHeader('Authorization'));
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'payment_link' && $job->variables['link'] === 'https://rzp.io/i/abc');
        $this->assertSame('pending', Payment::withoutTenancy()->where('gateway_link_id', 'plink_9')->firstOrFail()->status);
        $this->assertSame('partial', Invoice::withoutTenancy()->find($invoice->id)->status);
    }

    public function test_a_new_link_retires_the_old_one(): void
    {
        Bus::fake();
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plink_2', 'short_url' => 'https://rzp.io/i/2'])]);
        [$organization, $owner, $invoice] = $this->setUpClinic();
        $old = $this->pendingLink($invoice);

        $this->actingAs($owner, 'web')->post(route('app.invoices.payment-link', $invoice));

        $this->assertSame('superseded', Payment::withoutTenancy()->find($old->id)->status);
        $this->assertNotNull($organization);
    }

    public function test_a_gateway_failure_shows_a_message_and_records_nothing(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'bad']], 400)]);
        [, $owner, $invoice] = $this->setUpClinic();

        $this->actingAs($owner, 'web')->post(route('app.invoices.payment-link', $invoice))->assertSessionHas('error');

        $this->assertSame(0, Payment::withoutTenancy()->count());
    }

    public function test_an_online_refund_goes_through_razorpay_first(): void
    {
        Http::fake(['api.razorpay.com/v1/payments/pay_1/refund' => Http::response(['id' => 'rfnd_1', 'status' => 'processed'])]);
        [$organization, $owner, $invoice] = $this->setUpClinic();
        $this->pendingLink($invoice);
        app(BillingService::class)->captureGatewayPayment($organization->id, 'plink_1', 'pay_1');
        $payment = Payment::withoutTenancy()->firstOrFail();

        $this->actingAs($owner, 'web')->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '100', 'reason' => 'Goodwill'])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => $request['amount'] === 10000);
        $refund = Refund::withoutTenancy()->firstOrFail();
        $this->assertSame('rfnd_1', $refund->gateway_refund_id);
        $this->assertSame(10000, Invoice::withoutTenancy()->find($invoice->id)->refunded_paise);
    }

    public function test_when_razorpay_refuses_a_refund_nothing_is_recorded(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response([], 400)]);
        [$organization, $owner, $invoice] = $this->setUpClinic();
        $this->pendingLink($invoice);
        app(BillingService::class)->captureGatewayPayment($organization->id, 'plink_1', 'pay_1');
        $payment = Payment::withoutTenancy()->firstOrFail();

        $this->actingAs($owner, 'web')->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '100', 'reason' => 'x'])->assertSessionHas('error');

        $this->assertSame(0, Refund::withoutTenancy()->count());
    }

    public function test_keys_are_encrypted_masked_never_rendered_and_kept_when_left_blank(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->post(route('app.integrations.razorpay'), ['key_id' => 'rzp_live_KEYID1234', 'key_secret' => self::KEY_SECRET, 'webhook_secret' => self::WEBHOOK_SECRET, 'mode' => 'live', 'is_enabled' => 1])->assertSessionHas('success');

        $raw = (string) DB::table('organization_integrations')->where('organization_id', $organization->id)->value('config');
        $this->assertStringNotContainsString(self::KEY_SECRET, $raw);
        $this->assertStringNotContainsString('rzp_live_KEYID1234', $raw);

        $page = $this->get(route('app.integrations.index'))->assertOk();
        $page->assertDontSee(self::KEY_SECRET)->assertDontSee(self::WEBHOOK_SECRET)->assertDontSee('rzp_live_KEYID1234')->assertSee('••••1234');
        $page->assertSee(route('webhooks.razorpay', $organization->uuid));

        $this->post(route('app.integrations.razorpay'), ['key_id' => '', 'key_secret' => '', 'webhook_secret' => '', 'mode' => 'live', 'is_enabled' => 1]);
        $config = OrganizationIntegration::withoutTenancy()->where('organization_id', $organization->id)->firstOrFail()->config;
        $this->assertSame(self::KEY_SECRET, $config['key_secret']);
        $this->assertSame('rzp_live_KEYID1234', $config['key_id']);

        $this->assertArrayNotHasKey('config', OrganizationIntegration::withoutTenancy()->firstOrFail()->toArray());
        $logs = DB::table('audit_logs')->where('action', 'integrations.saved')->pluck('metadata')->implode(' ');
        $this->assertStringNotContainsString(self::KEY_SECRET, $logs);
        $this->assertNotNull(app(AuditLogger::class));
    }

    public function test_test_connection_reports_the_result(): void
    {
        [, $owner] = $this->setUpClinic();
        $this->actingAs($owner, 'web');

        Http::fakeSequence('api.razorpay.com/*')->push(['items' => []])->push([], 401);

        $this->post(route('app.integrations.razorpay.test'))->assertSessionHas('success');
        $this->assertSame('passed', OrganizationIntegration::withoutTenancy()->firstOrFail()->last_test_result);

        $this->post(route('app.integrations.razorpay.test'))->assertSessionHas('error');
        $this->assertSame('failed', OrganizationIntegration::withoutTenancy()->firstOrFail()->last_test_result);
    }

    public function test_only_people_with_the_permission_can_open_integrations(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web');

        $this->get(route('app.integrations.index'))->assertForbidden();
        $this->post(route('app.integrations.razorpay'), ['mode' => 'test'])->assertForbidden();
    }

    public function test_reconciliation_finds_every_kind_of_difference(): void
    {
        Http::fake(['api.razorpay.com/v1/payments*' => Http::response(['items' => [
            ['id' => 'pay_ok', 'amount' => 50000, 'status' => 'captured', 'created_at' => now()->timestamp],
            ['id' => 'pay_diff', 'amount' => 40000, 'status' => 'captured', 'created_at' => now()->timestamp],
            ['id' => 'pay_extra', 'amount' => 10000, 'status' => 'captured', 'created_at' => now()->timestamp],
            ['id' => 'pay_failed', 'amount' => 10000, 'status' => 'failed', 'created_at' => now()->timestamp],
        ]])]);
        [$organization, $owner, $invoice] = $this->setUpClinic();

        foreach (['pay_ok' => 50000, 'pay_diff' => 50000, 'pay_gone' => 50000] as $id => $amount) {
            Payment::withoutTenancy()->create([
                'organization_id' => $organization->id, 'invoice_id' => $invoice->id, 'amount_paise' => $amount,
                'mode' => 'razorpay', 'status' => 'captured', 'gateway_payment_id' => $id, 'paid_at' => now(),
            ]);
        }

        $response = $this->actingAs($owner, 'web')->get(route('app.reports.reconciliation'))->assertOk();
        $response->assertSee('pay_extra')->assertSee('pay_diff')->assertDontSee('pay_failed')->assertSee('Only in bills');

        $result = $response->viewData('result');
        $this->assertSame(1, $result['matched']);
        $this->assertCount(1, $result['missing_locally']);
        $this->assertCount(1, $result['missing_at_gateway']);
        $this->assertCount(1, $result['amount_mismatch']);
    }
}
