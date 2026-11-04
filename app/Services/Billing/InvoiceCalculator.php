<?php

namespace App\Services\Billing;

/**
 * Pure arithmetic in integer paise. Tax is added on top of the discounted price.
 */
final class InvoiceCalculator
{
    /**
     * @param  list<array{quantity: int, unit_paise: int, is_taxable: bool, gst_rate_bp: int}>  $lines
     * @return array{lines: list<array{quantity: int, unit_paise: int, is_taxable: bool, gst_rate_bp: int, discount_paise: int, tax_paise: int, line_total_paise: int}>, subtotal_paise: int, discount_paise: int, exempt_paise: int, taxable_paise: int, tax_paise: int, total_paise: int}
     */
    public function calculate(array $lines, int $discountPaise): array
    {
        $subtotal = 0;

        foreach ($lines as $line) {
            $subtotal += $line['quantity'] * $line['unit_paise'];
        }

        $discountPaise = min($discountPaise, $subtotal);
        $result = [];
        $allocated = 0;
        $exempt = 0;
        $taxable = 0;
        $tax = 0;

        foreach ($lines as $index => $line) {
            $gross = $line['quantity'] * $line['unit_paise'];

            // Spread the discount by value, and give the rounding remainder to the last line,
            // so line discounts always add up to exactly the invoice discount.
            $share = $index === array_key_last($lines)
                ? $discountPaise - $allocated
                : ($subtotal > 0 ? (int) floor($gross * $discountPaise / $subtotal) : 0);
            $allocated += $share;
            $base = $gross - $share;
            $lineTax = $line['is_taxable'] ? (int) round($base * $line['gst_rate_bp'] / 10000) : 0;

            if ($line['is_taxable']) {
                $taxable += $base;
            } else {
                $exempt += $base;
            }

            $tax += $lineTax;
            $result[] = $line + ['discount_paise' => $share, 'tax_paise' => $lineTax, 'line_total_paise' => $base + $lineTax];
        }

        return [
            'lines' => $result,
            'subtotal_paise' => $subtotal,
            'discount_paise' => $discountPaise,
            'exempt_paise' => $exempt,
            'taxable_paise' => $taxable,
            'tax_paise' => $tax,
            'total_paise' => $exempt + $taxable + $tax,
        ];
    }
}
