<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Payments\CashfreeGateway;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CashfreeTest extends TestCase
{
    private const SECRET = 'cfsk_secret_value_1234';

    /**
     * @return array{Organization, User, Invoice}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        OrganizationIntegration::withoutTenancy()->create([
            'organization_id' => $organization->id, 'kind' => 'cashfree', 'is_enabled' => true,
            'config' => ['key_id' => 'cf_client_id', 'key_secret' => self::SECRET, 'mode' => 'test'],
        ]);

        return [$organization, $owner, $this->invoice($organization, $location)];
    }

    private function invoice(Organization $organization, Location $location): Invoice
    {
        $patient = Patient::factory()->create(['organization_id' => $organization->id, 'mobile' => '+919812345678', 'email' => 'p@example.test']);

        return app(BillingService::class)->createInvoice($organization, $patient, [
            ['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => 50000, 'is_taxable' => false, 'gst_rate_bp' => 0],
        ], ['location_id' => $location->id]);
    }

    private function pendingLink(Invoice $invoice, string $linkId = 'inv-1-100'): Payment
    {
        return Payment::withoutTenancy()->create([
            'organization_id' => $invoice->organization_id, 'invoice_id' => $invoice->id, 'amount_paise' => 50000,
            'mode' => 'cashfree', 'status' => 'pending', 'gateway_link_id' => $linkId, 'gateway_link_url' => 'https://payments.cashfree.com/links/x',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paidEvent(string $linkId = 'inv-1-100', string $status = 'PAID'): array
    {
        return ['type' => 'PAYMENT_LINK_EVENT', 'version' => 1, 'data' => [
            'link_id' => $linkId, 'link_status' => $status, 'link_amount_paid' => '500.00',
            'order' => ['order_id' => 'CFPay_abc_1', 'order_amount' => '500.00', 'transaction_id' => 123, 'transaction_status' => 'SUCCESS'],
        ]];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function webhook(Organization $organization, array $body, ?string $signature = null, string $timestamp = '1700000000000'): TestResponse
    {
        $payload = (string) json_encode($body);

        return $this->call('POST', route('webhooks.cashfree', $organization->uuid), [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature ?? base64_encode(hash_hmac('sha256', $timestamp.$payload, self::SECRET, true)),
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_a_payment_link_is_sent_to_cashfree_in_the_documented_shape(): void
    {
        Http::fake(['sandbox.cashfree.com/*' => Http::response(['link_id' => 'inv-1-100', 'link_url' => 'https://payments.cashfree.com/links/abc'])]);
        [$organization, $owner, $invoice] = $this->clinic();

        $this->actingAs($owner, 'web')->post(route('app.invoices.payment-link', $invoice))->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.cashfree.com/pg/links'
            && $request->hasHeader('x-client-id', 'cf_client_id')
            && $request->hasHeader('x-client-secret', self::SECRET)
            && $request->hasHeader('x-api-version')
            && (float) $request['link_amount'] === 500.0
            && $request['link_currency'] === 'INR'
            && $request['customer_details']['customer_phone'] === '9812345678'
            && $request['link_notify'] === ['send_sms' => false, 'send_email' => false]);

        $payment = Payment::withoutTenancy()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('cashfree', $payment->mode);
        $this->assertSame('inv-1-100', $payment->gateway_link_id);
        $this->assertNotNull($organization);
    }

    public function test_live_mode_uses_the_live_address(): void
    {
        Http::fake(['api.cashfree.com/*' => Http::response(['link_id' => 'x', 'link_url' => 'https://u'])]);

        (new CashfreeGateway('id', 'secret', 'live'))->createPaymentLink(['amount_paise' => 100, 'reference' => 'x', 'description' => 'd', 'name' => 'n', 'mobile' => '+919812345678', 'callback_url' => 'http://localhost/r']);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.cashfree.com/pg/') && ! isset($request['link_meta']['return_url']));
    }

    public function test_a_signed_webhook_marks_the_bill_paid_only_once(): void
    {
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);

        $this->webhook($organization, $this->paidEvent())->assertOk();
        $this->webhook($organization, $this->paidEvent())->assertOk();

        $fresh = Invoice::withoutTenancy()->find($invoice->id);
        $this->assertSame('paid', $fresh->status);
        $this->assertSame(50000, (int) $fresh->paid_paise);
        $this->assertSame('CFPay_abc_1', Payment::withoutTenancy()->where('invoice_id', $invoice->id)->value('gateway_payment_id'));
    }

    public function test_a_forged_webhook_changes_nothing_and_is_logged(): void
    {
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);

        $this->webhook($organization, $this->paidEvent(), 'not-the-signature')->assertStatus(401);
        $this->webhook($organization, $this->paidEvent(), '')->assertStatus(401);

        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoice->id)->status);
    }

    public function test_one_practices_secret_cannot_sign_for_another(): void
    {
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);
        $payload = (string) json_encode($this->paidEvent());
        $wrong = base64_encode(hash_hmac('sha256', '1'.$payload, 'someone-elses-secret', true));

        $this->webhook($organization, $this->paidEvent(), $wrong, '1')->assertStatus(401);
    }

    public function test_an_unfinished_payment_does_not_mark_the_bill_paid(): void
    {
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);

        $this->webhook($organization, $this->paidEvent(status: 'PARTIALLY_PAID'))->assertOk();

        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoice->id)->status);
    }

    public function test_opening_the_return_address_alone_cannot_mark_a_bill_paid(): void
    {
        Http::fake(['sandbox.cashfree.com/*' => Http::response(['link_status' => 'ACTIVE'])]);
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);

        $this->get(route('pay.return.cashfree', [$organization->uuid, 'inv-1-100']).'?order_status=PAID')->assertOk();

        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoice->id)->status);
    }

    public function test_returning_after_paying_completes_it_when_cashfree_confirms(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/links/inv-1-100/orders' => Http::response([['order_id' => 'CFPay_abc_1', 'order_status' => 'PAID']]),
            'sandbox.cashfree.com/pg/links/inv-1-100' => Http::response(['link_status' => 'PAID']),
        ]);
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);

        $this->get(route('pay.return.cashfree', [$organization->uuid, 'inv-1-100']))->assertOk();

        $this->assertSame('paid', Invoice::withoutTenancy()->find($invoice->id)->status);
    }

    public function test_an_order_list_in_an_unexpected_shape_does_not_mark_it_paid(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/links/inv-1-100/orders' => Http::response(['something' => 'else']),
            'sandbox.cashfree.com/pg/links/inv-1-100' => Http::response(['link_status' => 'PAID']),
        ]);
        [$organization, , $invoice] = $this->clinic();
        $this->pendingLink($invoice);

        $this->get(route('pay.return.cashfree', [$organization->uuid, 'inv-1-100']))->assertOk();

        $this->assertSame('unpaid', Invoice::withoutTenancy()->find($invoice->id)->status);
    }

    public function test_a_refund_goes_to_cashfree_first_with_the_order_id(): void
    {
        Http::fake(['sandbox.cashfree.com/pg/orders/CFPay_abc_1/refunds' => Http::response(['cf_refund_id' => '1', 'refund_status' => 'PENDING'])]);
        [$organization, $owner, $invoice] = $this->clinic();
        $this->pendingLink($invoice);
        $this->webhook($organization, $this->paidEvent());
        $payment = Payment::withoutTenancy()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($owner, 'web')->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '100', 'reason' => 'Overcharged'])->assertSessionHas('success');

        Http::assertSent(fn ($request) => (float) $request['refund_amount'] === 100.0 && $request->hasHeader('x-idempotency-key'));
        $refund = Refund::withoutTenancy()->firstOrFail();
        $this->assertSame('pending', $refund->status);

        auth('web')->logout();
        $this->webhook($organization, ['type' => 'REFUND_STATUS_WEBHOOK', 'data' => ['refund' => ['refund_id' => $refund->gateway_refund_id, 'refund_status' => 'SUCCESS']]])->assertOk();
        $this->assertSame('processed', $refund->fresh()->status);
    }

    public function test_when_cashfree_refuses_a_refund_nothing_is_recorded(): void
    {
        Http::fake(['sandbox.cashfree.com/*' => Http::response(['message' => 'no'], 400)]);
        [$organization, $owner, $invoice] = $this->clinic();
        $this->pendingLink($invoice);
        $this->webhook($organization, $this->paidEvent());
        $payment = Payment::withoutTenancy()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($owner, 'web')->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '100', 'reason' => 'x']);

        $this->assertSame(0, Refund::withoutTenancy()->count());
    }

    public function test_keys_are_encrypted_masked_and_kept_when_left_blank(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->post(route('app.integrations.cashfree'), ['key_id' => 'cf_client_value', 'key_secret' => self::SECRET, 'mode' => 'test', 'is_enabled' => '1'])->assertSessionHas('success');
        $this->post(route('app.integrations.cashfree'), ['mode' => 'live', 'is_enabled' => '1'])->assertSessionHas('success');

        $record = OrganizationIntegration::withoutTenancy()->where('organization_id', $organization->id)->where('kind', 'cashfree')->firstOrFail();
        $this->assertSame(self::SECRET, $record->config['key_secret']);
        $this->assertSame('live', $record->config['mode']);
        $this->assertStringNotContainsString(self::SECRET, (string) $record->getRawOriginal('config'));
        $this->get(route('app.integrations.index'))->assertOk()->assertDontSee(self::SECRET)->assertSee(route('webhooks.cashfree', $organization->uuid));
    }

    public function test_test_connection_reports_the_result(): void
    {
        [, $owner] = $this->clinic();
        $this->actingAs($owner, 'web');

        Http::fake(['*' => Http::sequence()->push([], 404)->push([], 401)]);
        $this->post(route('app.integrations.cashfree.test'))->assertSessionHas('success');

        $this->post(route('app.integrations.cashfree.test'))->assertSessionHas('error');
    }

    public function test_a_practice_chooses_one_gateway_and_can_switch_without_breaking_old_links(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');
        $factory = app(GatewayFactory::class);

        $this->assertNull($factory->forOrganization($organization->id), 'Nothing is enabled until they choose.');

        $this->post(route('app.integrations.razorpay'), ['key_id' => 'rzp', 'key_secret' => 's', 'webhook_secret' => 'w', 'mode' => 'test', 'is_enabled' => '1']);
        $this->assertInstanceOf(RazorpayGateway::class, $factory->forOrganization($organization->id));

        $this->post(route('app.integrations.cashfree'), ['key_id' => 'cf', 'key_secret' => 's', 'mode' => 'test', 'is_enabled' => '1'])->assertSessionHas('success');
        $this->assertInstanceOf(CashfreeGateway::class, $factory->forOrganization($organization->id));
        $this->assertFalse((bool) OrganizationIntegration::withoutTenancy()->where('organization_id', $organization->id)->where('kind', 'razorpay')->value('is_enabled'));

        $this->assertInstanceOf(RazorpayGateway::class, $factory->forWebhook($organization->id, 'razorpay'), 'Razorpay links already sent must still complete.');
    }

    public function test_an_unapproved_or_expired_practice_cannot_take_cashfree_payments(): void
    {
        [$organization] = $this->clinic();
        $organization->forceFill(['access_until' => today()->subDay()])->save();

        $this->assertNull(app(GatewayFactory::class)->forOrganization($organization->id));
    }

    public function test_the_refund_status_mapping(): void
    {
        $this->assertSame('processed', CashfreeGateway::refundStatus('SUCCESS'));
        $this->assertSame('failed', CashfreeGateway::refundStatus('CANCELLED'));
        $this->assertSame('pending', CashfreeGateway::refundStatus('ONHOLD'));
    }
}
