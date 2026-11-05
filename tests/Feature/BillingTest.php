<?php

namespace Tests\Feature;

use App\Enums\VisitType;
use App\Exceptions\BillingException;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\DoctorFee;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServiceItem;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingService;
use App\Services\Billing\FeeResolver;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingTest extends TestCase
{
    /**
     * @return array{Organization, User, Location, Patient}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();

        return [$organization, $owner, $location, Patient::factory()->create(['organization_id' => $organization->id, 'email' => 'p@example.test'])];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bill(Patient $patient, array $overrides = []): array
    {
        return $overrides + [
            'patient_id' => $patient->id,
            'items_json' => json_encode([['description' => 'Consultation', 'quantity' => 1, 'unit' => '500']]),
        ];
    }

    private function invoice(Organization $organization, Location $location, Patient $patient, int $unit = 50000): Invoice
    {
        return app(BillingService::class)->createInvoice($organization, $patient, [
            ['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => $unit, 'is_taxable' => false, 'gst_rate_bp' => 0],
        ], ['location_id' => $location->id]);
    }

    public function test_a_consultation_gets_a_receipt_and_cash_marks_it_paid(): void
    {
        [, $owner, , $patient] = $this->clinic();

        $this->actingAs($owner, 'web')->post(route('app.invoices.store'), $this->bill($patient, ['pay_mode' => 'cash', 'pay_amount' => '500']))->assertRedirect();

        $invoice = Invoice::withoutTenancy()->firstOrFail();
        $this->assertSame('receipt', $invoice->kind);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(50000, $invoice->paid_paise);
        $this->assertStringStartsWith('RCP/', $invoice->number);
        $this->get(route('app.invoices.show', $invoice))->assertOk()->assertSee('Receipt')->assertDontSee('Tax invoice');
    }

    public function test_a_taxable_item_turns_it_into_a_gst_invoice_and_the_browser_cannot_change_the_tax(): void
    {
        [$organization, $owner, , $patient] = $this->clinic();
        $organization->forceFill(['gstin' => '19AAAAA0000A1Z5'])->save();
        $item = ServiceItem::withoutTenancy()->create(['organization_id' => $organization->id, 'name' => 'Cosmetic peel', 'price_paise' => 100000, 'is_taxable' => true, 'gst_rate_bp' => 1800]);

        $this->actingAs($owner, 'web')->post(route('app.invoices.store'), $this->bill($patient, [
            'items_json' => json_encode([['service_item_id' => $item->id, 'description' => 'Peel', 'quantity' => 1, 'unit' => '1000', 'is_taxable' => false, 'gst_rate_bp' => 0]]),
        ]))->assertRedirect();

        $invoice = Invoice::withoutTenancy()->firstOrFail();
        $this->assertSame('gst_invoice', $invoice->kind);
        $this->assertSame(18000, $invoice->tax_paise);
        $this->assertSame(118000, $invoice->total_paise);
        $this->assertSame('19AAAAA0000A1Z5', $invoice->gstin_snapshot);
        $this->assertStringStartsWith('INV/', $invoice->number);
    }

    public function test_numbers_run_in_sequence_per_location_and_organization(): void
    {
        [$a, , $locationA, $patientA] = $this->clinic();
        [$b, , $locationB, $patientB] = $this->clinic();
        $secondA = Location::factory()->create(['organization_id' => $a->id]);

        $first = $this->invoice($a, $locationA, $patientA)->number;
        $second = $this->invoice($a, $locationA, $patientA)->number;
        $otherLocation = $this->invoice($a, $secondA, $patientA)->number;
        $otherOrganization = $this->invoice($b, $locationB, $patientB)->number;

        $this->assertStringEndsWith('/00001', $first);
        $this->assertStringEndsWith('/00002', $second);
        $this->assertStringEndsWith('/00001', $otherLocation);
        $this->assertStringEndsWith('/00001', $otherOrganization);
    }

    public function test_a_discount_needs_a_reason_and_the_right_permission(): void
    {
        [$organization, $owner, $location, $patient] = $this->clinic();
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web')->post(route('app.invoices.store'), $this->bill($patient, ['discount' => '50', 'discount_reason' => 'x']))->assertForbidden();

        $this->actingAs($owner, 'web')->from(route('app.invoices.create'))->post(route('app.invoices.store'), $this->bill($patient, ['discount' => '50']))
            ->assertSessionHasErrors(['discount_reason' => 'Give a reason for the discount.']);
        $this->assertSame(0, Invoice::withoutTenancy()->count());

        $this->post(route('app.invoices.store'), $this->bill($patient, ['discount' => '50', 'discount_reason' => 'Staff family']))->assertRedirect();
        $this->assertSame(45000, Invoice::withoutTenancy()->firstOrFail()->total_paise);
    }

    public function test_part_payment_tracks_dues_and_overpayment_is_refused(): void
    {
        [$organization, $owner, $location, $patient] = $this->clinic();
        $invoice = $this->invoice($organization, $location, $patient);
        $this->actingAs($owner, 'web');

        $this->post(route('app.invoices.pay', $invoice), ['mode' => 'cash', 'amount' => '200'])->assertSessionHas('success');
        $this->assertSame('partial', Invoice::withoutTenancy()->find($invoice->id)->status);
        $this->get(route('app.dues.index'))->assertOk()->assertSee($patient->name)->assertSee('₹300.00');

        $this->post(route('app.invoices.pay', $invoice), ['mode' => 'upi', 'amount' => '300.01'])->assertSessionHas('error');
        $this->post(route('app.invoices.pay', $invoice), ['mode' => 'upi', 'amount' => '300'])->assertSessionHas('success');

        $paid = Invoice::withoutTenancy()->find($invoice->id);
        $this->assertSame('paid', $paid->status);
        $this->assertSame(0, $paid->duePaise());
    }

    public function test_cash_refund_needs_permission_a_reason_and_cannot_exceed_the_payment(): void
    {
        [$organization, $owner, $location, $patient] = $this->clinic();
        $invoice = $this->invoice($organization, $location, $patient);
        $payment = app(BillingService::class)->recordPayment($invoice, 50000, 'cash');
        $receptionist = $this->addMember($organization, ['receptionist'], location: $location);

        $this->actingAs($receptionist, 'web')->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '10', 'reason' => 'x'])->assertForbidden();

        $this->actingAs($owner, 'web');
        $this->from(route('app.invoices.show', $invoice))->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '10'])->assertSessionHasErrors('reason');
        $this->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '501', 'reason' => 'x'])->assertSessionHas('error');
        $this->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '500', 'reason' => 'Doctor absent'])->assertSessionHas('success');

        $this->assertSame('refunded', Invoice::withoutTenancy()->find($invoice->id)->status);
        $this->post(route('app.invoices.refund', $invoice), ['payment_id' => $payment->id, 'amount' => '1', 'reason' => 'again'])->assertSessionHas('error');
    }

    public function test_a_bill_with_money_on_it_cannot_be_cancelled_until_refunded(): void
    {
        [$organization, $owner, $location, $patient] = $this->clinic();
        $invoice = $this->invoice($organization, $location, $patient);
        $payment = app(BillingService::class)->recordPayment($invoice, 50000, 'cash');
        $this->actingAs($owner, 'web');

        $this->post(route('app.invoices.cancel', $invoice), ['reason' => 'Mistake'])->assertSessionHas('error');

        app(BillingService::class)->refund($payment, 50000, 'Mistake');
        $this->post(route('app.invoices.cancel', $invoice), ['reason' => 'Mistake'])->assertSessionHas('success');

        $cancelled = Invoice::withoutTenancy()->find($invoice->id);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(0, $cancelled->duePaise());

        $this->expectException(BillingException::class);
        app(BillingService::class)->recordPayment($cancelled, 100, 'cash');
    }

    public function test_the_fee_fills_in_per_doctor_with_a_fallback_and_the_follow_up_window(): void
    {
        [$organization, , $location, $patient] = $this->clinic();
        $doctor = $this->addMember($organization, ['doctor'], location: $location);
        $organization->forceFill(['settings' => ['follow_up_window_days' => 7, 'follow_up_discount_percent' => 100]])->save();
        DoctorFee::withoutTenancy()->create(['organization_id' => $organization->id, 'doctor_user_id' => null, 'visit_type' => 'new', 'amount_paise' => 30000]);
        DoctorFee::withoutTenancy()->create(['organization_id' => $organization->id, 'doctor_user_id' => $doctor->id, 'visit_type' => 'follow_up', 'amount_paise' => 20000]);
        $fees = app(FeeResolver::class);

        $this->assertSame(30000, $fees->resolve($organization, $doctor->id, VisitType::New, $patient->id)['amount_paise']);
        $this->assertSame(20000, $fees->resolve($organization, $doctor->id, VisitType::FollowUp, $patient->id)['amount_paise']);

        Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'visited_on' => now()->subDays(3)->toDateString()]);
        $this->assertSame(0, $fees->resolve($organization, $doctor->id, VisitType::FollowUp, $patient->id)['amount_paise']);

        $this->travel(5)->days();
        $this->assertSame(20000, $fees->resolve($organization, $doctor->id, VisitType::FollowUp, $patient->id)['amount_paise']);
    }

    public function test_fee_endpoint_and_settings_page_work_together(): void
    {
        [, $owner, , $patient] = $this->clinic();
        $this->actingAs($owner, 'web');

        $this->post(route('app.billing-settings.fees'), [
            'fees' => ['default' => ['new' => '350', 'follow_up' => '']],
            'follow_up_window_days' => 0, 'follow_up_discount_percent' => 100,
        ])->assertSessionHasNoErrors();

        $this->getJson(route('app.invoices.fee', ['visit_type' => 'new', 'patient_id' => $patient->id]))->assertOk()->assertJson(['amount' => '350.00']);
        $this->get(route('app.billing-settings.index'))->assertOk()->assertSee('350.00');
    }

    public function test_a_stranger_doctor_in_fee_settings_is_refused(): void
    {
        [, $owner] = $this->clinic();
        $stranger = User::factory()->create();

        $this->actingAs($owner, 'web')->from(route('app.billing-settings.index'))->post(route('app.billing-settings.fees'), [
            'fees' => [$stranger->id => ['new' => '100']], 'follow_up_window_days' => 0, 'follow_up_discount_percent' => 100,
        ])->assertSessionHasErrors('fees');

        $this->assertSame(0, DoctorFee::withoutTenancy()->count());
    }

    public function test_pdf_and_email_work_and_bills_are_tenant_isolated(): void
    {
        $this->clinicsHaveOwnEmail();
        Bus::fake();
        [$organization, $owner, $location, $patient] = $this->clinic();
        [, $stranger] = $this->clinic();
        $invoice = $this->invoice($organization, $location, $patient);

        $this->actingAs($stranger, 'web');
        $this->get(route('app.invoices.show', $invoice->id))->assertNotFound();
        $this->post(route('app.invoices.pay', $invoice->id), ['mode' => 'cash', 'amount' => '1'])->assertNotFound();
        $this->get(route('app.invoices.pdf', $invoice->id))->assertNotFound();

        $this->actingAs($owner, 'web');
        $this->assertStringStartsWith('%PDF', $this->get(route('app.invoices.pdf', $invoice))->assertOk()->getContent());
        $this->post(route('app.invoices.email', $invoice))->assertSessionHas('success');
        Bus::assertDispatched(SendInvoiceEmail::class);
    }

    public function test_the_email_job_sends_the_pdf(): void
    {
        $this->clinicsHaveOwnEmail();
        Mail::fake();
        [$organization, , $location, $patient] = $this->clinic();
        $invoice = $this->invoice($organization, $location, $patient);

        SendInvoiceEmail::dispatchSync($organization->id, $invoice->id);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('p@example.test'));
    }

    public function test_a_nurse_cannot_reach_billing(): void
    {
        [$organization, , $location, $patient] = $this->clinic();
        $nurse = $this->addMember($organization, ['nurse'], location: $location);
        $invoice = $this->invoice($organization, $location, $patient);

        $this->actingAs($nurse, 'web');

        $this->get(route('app.invoices.index'))->assertForbidden();
        $this->get(route('app.invoices.show', $invoice))->assertForbidden();
        $this->assertSame(0, Payment::withoutTenancy()->count());
    }

    public function test_csv_export_includes_bills(): void
    {
        [$organization, $owner, $location, $patient] = $this->clinic();
        $this->invoice($organization, $location, $patient);

        $csv = $this->actingAs($owner, 'web')->get(route('app.invoices.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('500.00', $csv);
    }
}
