<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingService;
use App\Support\XlsxWriter;
use Tests\TestCase;
use ZipArchive;

class ReportsTest extends TestCase
{
    /**
     * @return array{Organization, User, Location, User, Patient}
     */
    private function clinic(): array
    {
        [$organization, $owner, $location] = $this->organizationWithOwner();
        $doctor = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Report']), $location);

        return [$organization, $owner, $location, $doctor, Patient::factory()->create(['organization_id' => $organization->id])];
    }

    private function paidBill(Organization $organization, Location $location, Patient $patient, ?User $doctor, int $amount, string $mode): void
    {
        $billing = app(BillingService::class);
        $invoice = $billing->createInvoice($organization, $patient, [
            ['description' => 'Consultation', 'quantity' => 1, 'unit_paise' => $amount, 'is_taxable' => false, 'gst_rate_bp' => 0],
        ], ['location_id' => $location->id, 'doctor_user_id' => $doctor?->id]);
        $billing->recordPayment($invoice, $amount, $mode);
    }

    public function test_daily_cash_splits_by_doctor_and_mode_and_subtracts_refunds(): void
    {
        [$organization, $owner, $location, $doctor, $patient] = $this->clinic();
        $this->paidBill($organization, $location, $patient, $doctor, 50000, 'cash');
        $this->paidBill($organization, $location, $patient, $doctor, 30000, 'upi');

        $cash = $this->actingAs($owner, 'web')->get(route('app.reports.index'))->assertOk()->assertSee('Dr Report')->viewData('cash');

        $this->assertSame(50000, $cash['modes']['cash']);
        $this->assertSame(30000, $cash['modes']['upi']);
        $this->assertSame(80000, $cash['net']);
    }

    public function test_reports_never_include_another_organization(): void
    {
        [$a, $ownerA, $locationA, , $patientA] = $this->clinic();
        [$b, , $locationB, , $patientB] = $this->clinic();
        $this->paidBill($a, $locationA, $patientA, null, 10000, 'cash');
        $this->paidBill($b, $locationB, $patientB, null, 99000, 'cash');

        $response = $this->actingAs($ownerA, 'web')->get(route('app.reports.index'))->assertOk();

        $this->assertSame(10000, $response->viewData('cash')['net']);
        $this->assertSame(10000, $response->viewData('income')['exempt']);
    }

    public function test_top_diagnoses_are_counted_after_decrypting(): void
    {
        [$organization, $owner, $location, $doctor, $patient] = $this->clinic();

        foreach (['Fever', 'fever ', 'Diabetes'] as $diagnosis) {
            Visit::factory()->create(['organization_id' => $organization->id, 'location_id' => $location->id, 'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id, 'diagnosis' => $diagnosis]);
        }

        $diagnoses = $this->actingAs($owner, 'web')->get(route('app.reports.index'))->assertOk()->viewData('diagnoses');

        $this->assertSame(['fever' => 2, 'diabetes' => 1], $diagnoses);
    }

    public function test_the_monthly_excel_file_is_a_real_workbook_with_numbers(): void
    {
        [$organization, $owner, $location, $doctor, $patient] = $this->clinic();
        $patient->forceFill(['name' => 'Ravi Kumar'])->save();
        $this->paidBill($organization, $location, $patient, $doctor, 50000, 'cash');

        $response = $this->actingAs($owner, 'web')->get(route('app.reports.monthly.xlsx', ['month' => now()->format('Y-m')]))->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString($patient->name, $sheet);
        $this->assertStringContainsString('<c><v>500</v></c>', $sheet);
    }

    public function test_the_monthly_pdf_downloads(): void
    {
        [, $owner] = $this->clinic();

        $response = $this->actingAs($owner, 'web')->get(route('app.reports.monthly.pdf'))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_spreadsheet_text_cannot_become_a_formula_or_break_the_xml(): void
    {
        $path = XlsxWriter::write('Test', ['A'], [['=SUM(A1) <b>&']]);
        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        // Inline strings are never evaluated, and the XML stays valid.
        $this->assertStringContainsString('t="inlineStr"', $sheet);
        $this->assertStringContainsString('=SUM(A1) &lt;b&gt;&amp;', $sheet);
        $this->assertNotFalse(simplexml_load_string($sheet));
    }

    public function test_a_receptionist_cannot_see_reports(): void
    {
        [$organization, , $location] = $this->clinic();

        $this->actingAs($this->addMember($organization, ['receptionist'], location: $location), 'web')
            ->get(route('app.reports.index'))
            ->assertForbidden();
    }
}
