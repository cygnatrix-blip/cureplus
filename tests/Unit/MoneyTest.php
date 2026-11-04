<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[TestWith(['500', 50000])]
    #[TestWith(['500.5', 50050])]
    #[TestWith(['0.07', 7])]
    #[TestWith(['19.99', 1999])]
    #[TestWith(['', 0])]
    #[TestWith([null, 0])]
    public function test_rupees_convert_to_paise_without_float_errors(?string $input, int $expected): void
    {
        $this->assertSame($expected, Money::toPaise($input));
    }

    #[TestWith(['-5'])]
    #[TestWith(['1.234'])]
    #[TestWith(['abc'])]
    public function test_bad_amounts_are_refused(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toPaise($input);
    }

    public function test_formatting_uses_indian_digit_groups(): void
    {
        $this->assertSame('₹1,00,000.00', Money::format(10000000));
        $this->assertSame('₹12,34,567.89', Money::format(123456789));
        $this->assertSame('₹999.05', Money::format(99905));
        $this->assertSame('₹0.00', Money::format(0));
    }
}
