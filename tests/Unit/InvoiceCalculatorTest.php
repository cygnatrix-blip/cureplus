<?php

namespace Tests\Unit;

use App\Services\Billing\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    /**
     * @return array{quantity: int, unit_paise: int, is_taxable: bool, gst_rate_bp: int}
     */
    private function line(int $unit, int $quantity = 1, int $rate = 0): array
    {
        return ['quantity' => $quantity, 'unit_paise' => $unit, 'is_taxable' => $rate > 0, 'gst_rate_bp' => $rate];
    }

    public function test_a_plain_consultation_is_exempt(): void
    {
        $result = (new InvoiceCalculator)->calculate([$this->line(50000)], 0);

        $this->assertSame(50000, $result['exempt_paise']);
        $this->assertSame(0, $result['taxable_paise']);
        $this->assertSame(0, $result['tax_paise']);
        $this->assertSame(50000, $result['total_paise']);
    }

    public function test_tax_is_charged_only_on_taxable_lines_and_reported_separately(): void
    {
        $result = (new InvoiceCalculator)->calculate([$this->line(50000), $this->line(100000, 1, 1800)], 0);

        $this->assertSame(50000, $result['exempt_paise']);
        $this->assertSame(100000, $result['taxable_paise']);
        $this->assertSame(18000, $result['tax_paise']);
        $this->assertSame(168000, $result['total_paise']);
    }

    public function test_a_discount_lowers_the_taxable_amount_and_adds_up_exactly(): void
    {
        $result = (new InvoiceCalculator)->calculate([$this->line(100000), $this->line(100000, 1, 1800), $this->line(100000)], 10000);

        $this->assertSame(10000, array_sum(array_column($result['lines'], 'discount_paise')));
        $this->assertSame(96667, $result['taxable_paise']);
        $this->assertSame(17400, $result['tax_paise']);
        $this->assertSame($result['exempt_paise'] + $result['taxable_paise'] + $result['tax_paise'], $result['total_paise']);
    }

    public function test_a_discount_cannot_exceed_the_bill(): void
    {
        $result = (new InvoiceCalculator)->calculate([$this->line(5000)], 99999);

        $this->assertSame(5000, $result['discount_paise']);
        $this->assertSame(0, $result['total_paise']);
    }
}
