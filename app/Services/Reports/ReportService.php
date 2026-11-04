<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Every query here goes through the tenant scope. Cash reports count money on the
 * day it moved; income reports count bills on the day they were issued.
 */
class ReportService
{
    /**
     * @return array{rows: list<array{doctor: string, modes: array<string, int>, refunds: int, net: int}>, modes: array<string, int>, refunds: int, net: int}
     */
    public function dailyCash(CarbonImmutable $date, ?int $locationId = null): array
    {
        $payments = Payment::query()
            ->where('status', 'captured')
            ->whereBetween('paid_at', [$date->startOfDay(), $date->endOfDay()])
            ->whereHas('invoice', fn ($query) => $query->when($locationId, fn ($query) => $query->where('location_id', $locationId)))
            ->with('invoice:id,doctor_user_id')
            ->get(['id', 'invoice_id', 'amount_paise', 'mode']);

        $refunds = Refund::query()
            ->where('status', '!=', 'failed')
            ->whereBetween('refunded_at', [$date->startOfDay(), $date->endOfDay()])
            ->with('payment.invoice:id,doctor_user_id,location_id')
            ->get()
            ->filter(fn (Refund $refund) => $locationId === null || (int) $refund->payment?->invoice?->location_id === $locationId);

        $doctorNames = User::query()
            ->whereIn('id', $payments->pluck('invoice.doctor_user_id')->merge($refunds->pluck('payment.invoice.doctor_user_id'))->filter()->unique())
            ->pluck('name', 'id');

        $rows = [];
        $modeTotals = [];

        foreach ($payments as $payment) {
            $doctorId = (int) $payment->invoice?->doctor_user_id;
            $rows[$doctorId]['modes'][$payment->mode] = ($rows[$doctorId]['modes'][$payment->mode] ?? 0) + $payment->amount_paise;
            $modeTotals[$payment->mode] = ($modeTotals[$payment->mode] ?? 0) + $payment->amount_paise;
        }

        foreach ($refunds as $refund) {
            $doctorId = (int) $refund->payment?->invoice?->doctor_user_id;
            $rows[$doctorId]['refunds'] = ($rows[$doctorId]['refunds'] ?? 0) + $refund->amount_paise;
        }

        $result = [];

        foreach ($rows as $doctorId => $row) {
            $modes = $row['modes'] ?? [];
            $refunded = $row['refunds'] ?? 0;
            $result[] = ['doctor' => $doctorNames[$doctorId] ?? 'No doctor', 'modes' => $modes, 'refunds' => $refunded, 'net' => array_sum($modes) - $refunded];
        }

        $totalRefunds = (int) $refunds->sum('amount_paise');

        return ['rows' => $result, 'modes' => $modeTotals, 'refunds' => $totalRefunds, 'net' => array_sum($modeTotals) - $totalRefunds];
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function invoicesForMonth(CarbonImmutable $month): Collection
    {
        return Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issued_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->with(['patient:id,name', 'doctor:id,name'])
            ->orderBy('issued_at')
            ->get()
            ->toBase();
    }

    /**
     * @return array{exempt: int, taxable: int, tax: int, total: int, collected: int, refunded: int}
     */
    public function incomeSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('COALESCE(SUM(exempt_paise),0) exempt, COALESCE(SUM(taxable_paise),0) taxable, COALESCE(SUM(tax_paise),0) tax, COALESCE(SUM(total_paise),0) total, COALESCE(SUM(paid_paise),0) collected, COALESCE(SUM(refunded_paise),0) refunded')
            ->toBase()
            ->first();

        return [
            'exempt' => (int) $row->exempt,
            'taxable' => (int) $row->taxable,
            'tax' => (int) $row->tax,
            'total' => (int) $row->total,
            'collected' => (int) $row->collected,
            'refunded' => (int) $row->refunded,
        ];
    }

    /**
     * Revenue for revenue sharing: what patients actually paid, less refunds.
     *
     * @return list<array{doctor: string, bills: int, billed: int, collected: int}>
     */
    public function doctorRevenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('doctor_user_id, COUNT(*) bills, SUM(total_paise) billed, SUM(paid_paise - refunded_paise) collected')
            ->groupBy('doctor_user_id')
            ->toBase()
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('doctor_user_id')->filter())->pluck('name', 'id');

        return $rows->map(fn (object $row) => [
            'doctor' => $names[$row->doctor_user_id] ?? 'No doctor',
            'bills' => (int) $row->bills,
            'billed' => (int) $row->billed,
            'collected' => (int) $row->collected,
        ])->sortByDesc('collected')->values()->all();
    }

    /**
     * @return array{new: int, seen: int}
     */
    public function patientCounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'new' => Patient::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'seen' => Visit::query()->whereBetween('visited_on', [$from->startOfDay(), $to->endOfDay()])->distinct()->count('patient_id'),
        ];
    }

    /**
     * Diagnoses are encrypted, so they can only be counted after decrypting here.
     * Capped by date range and row count to keep it safe on shared hosting.
     *
     * @return array<string, int>
     */
    public function topDiagnoses(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        $counts = [];

        Visit::query()
            ->whereBetween('visited_on', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotNull('diagnosis')
            ->select(['id', 'diagnosis'])
            ->limit(20000)
            ->get()
            ->each(function (Visit $visit) use (&$counts): void {
                $name = mb_strtolower(trim((string) $visit->diagnosis));

                if ($name !== '') {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            });

        arsort($counts);

        return array_slice($counts, 0, $limit, true);
    }

    /**
     * @param  list<array{id: string, amount_paise: int, status: string, created_at: int}>  $gatewayPayments
     * @return array{matched: int, missing_locally: list<array{id: string, amount_paise: int, status: string, created_at: int}>, missing_at_gateway: list<Payment>, amount_mismatch: list<array{id: string, local: int, gateway: int}>}
     */
    public function reconcile(CarbonImmutable $from, CarbonImmutable $to, array $gatewayPayments): array
    {
        $local = Payment::query()
            ->where('mode', 'razorpay')
            ->where('status', 'captured')
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('invoice:id,number')
            ->get();

        $captured = collect($gatewayPayments)->where('status', 'captured')->keyBy('id');
        $matched = 0;
        $mismatch = [];
        $missingAtGateway = [];

        foreach ($local as $payment) {
            $remote = $captured->get((string) $payment->gateway_payment_id);

            if ($remote === null) {
                $missingAtGateway[] = $payment;
            } elseif ($remote['amount_paise'] !== $payment->amount_paise) {
                $mismatch[] = ['id' => $remote['id'], 'local' => $payment->amount_paise, 'gateway' => $remote['amount_paise']];
            } else {
                $matched++;
            }
        }

        $knownIds = $local->pluck('gateway_payment_id')->filter()->all();

        return [
            'matched' => $matched,
            'missing_locally' => $captured->reject(fn (array $remote) => in_array($remote['id'], $knownIds, true))->values()->all(),
            'missing_at_gateway' => $missingAtGateway,
            'amount_mismatch' => $mismatch,
        ];
    }
}
