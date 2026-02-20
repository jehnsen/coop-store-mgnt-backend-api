<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Pure computation service — no database writes.
 *
 * Computes diminishing-balance (reducing-balance) amortization schedules
 * for MPC lending operations.  All monetary values are in CENTAVOS (integers).
 */
class AmortizationService
{
    /**
     * Compute a full diminishing-balance amortization schedule.
     *
     * @param  int     $principalCentavos  Loan principal in centavos.
     * @param  float   $monthlyRate        Monthly interest rate (e.g. 0.015 = 1.5 %).
     * @param  int     $termMonths         Number of monthly periods.
     * @param  Carbon  $firstPaymentDate   Date of the first payment.
     * @param  string  $interval           'monthly' | 'semi_monthly' | 'weekly'
     *
     * @return array{
     *     schedule: list<array>,
     *     total_interest: int,
     *     total_payable: int,
     *     emi_centavos: int,
     * }
     */
    public function computeDiminishingBalance(
        int    $principalCentavos,
        float  $monthlyRate,
        int    $termMonths,
        Carbon $firstPaymentDate,
        string $interval = 'monthly',
    ): array {
        if ($principalCentavos <= 0) {
            throw new InvalidArgumentException('Principal must be greater than zero.');
        }
        if ($monthlyRate <= 0) {
            throw new InvalidArgumentException('Monthly rate must be greater than zero.');
        }
        if ($termMonths <= 0) {
            throw new InvalidArgumentException('Term months must be greater than zero.');
        }

        // Adjust rate and periods for non-monthly intervals
        [$periodicRate, $totalPeriods] = $this->resolvePeriodicRate($monthlyRate, $termMonths, $interval);

        // EMI = P × [r(1+r)^n] / [(1+r)^n − 1]
        $emi = $this->computeEMI($principalCentavos, $periodicRate, $totalPeriods);

        $dueDates      = $this->getDueDates($firstPaymentDate, $totalPeriods, $interval);
        $schedule      = [];
        $balance       = $principalCentavos;
        $totalInterest = 0;

        for ($i = 0; $i < $totalPeriods; $i++) {
            $paymentNumber = $i + 1;
            $beginBalance  = $balance;

            $interestDue = (int) round($balance * $periodicRate);
            $principalDue = $emi - $interestDue;

            // Last period: absorb any rounding remainder so balance hits exactly 0
            if ($paymentNumber === $totalPeriods) {
                $principalDue = $balance;
                $emi          = $principalDue + $interestDue;
            }

            $endBalance = $beginBalance - $principalDue;
            if ($endBalance < 0) {
                $endBalance = 0;
            }

            $totalInterest += $interestDue;

            $schedule[] = [
                'payment_number'    => $paymentNumber,
                'due_date'          => $dueDates[$i]->toDateString(),
                'beginning_balance' => $beginBalance,
                'principal_due'     => $principalDue,
                'interest_due'      => $interestDue,
                'total_due'         => $principalDue + $interestDue,
                'ending_balance'    => $endBalance,
            ];

            $balance = $endBalance;
        }

        return [
            'schedule'       => $schedule,
            'total_interest' => $totalInterest,
            'total_payable'  => $principalCentavos + $totalInterest,
            'emi_centavos'   => $this->computeEMI($principalCentavos, $periodicRate, $totalPeriods),
        ];
    }

    /**
     * Compute the Equal Monthly (Periodic) Instalment in centavos.
     *  EMI = P × [r(1+r)^n] / [(1+r)^n − 1]
     */
    public function computeEMI(int $principalCentavos, float $periodicRate, int $periods): int
    {
        $factor = (1 + $periodicRate) ** $periods;
        return (int) ceil($principalCentavos * ($periodicRate * $factor) / ($factor - 1));
    }

    /**
     * Generate an array of Carbon due dates.
     *
     * @return Carbon[]
     */
    public function getDueDates(Carbon $firstPaymentDate, int $periods, string $interval): array
    {
        $dates   = [];
        $current = $firstPaymentDate->copy();

        for ($i = 0; $i < $periods; $i++) {
            $dates[] = $current->copy();

            $current = match ($interval) {
                'weekly'       => $current->addWeek(),
                'semi_monthly' => $current->addDays(15),
                default        => $current->addMonth(),
            };
        }

        return $dates;
    }

    /**
     * Convert monthly rate and term into periodic rate + total periods
     * based on payment interval.
     *
     * @return array{float, int}  [periodicRate, totalPeriods]
     */
    private function resolvePeriodicRate(float $monthlyRate, int $termMonths, string $interval): array
    {
        return match ($interval) {
            // Equivalent periodic rate: (1 + monthlyRate)^(1/periodsPerMonth) - 1
            // Weekly: ~4.33 weeks per month  →  exponent = 1/4.33
            'weekly'       => [pow(1 + $monthlyRate, 1 / 4.33) - 1, $termMonths * 4],
            // Semi-monthly: 2 periods per month  →  exponent = 1/2
            'semi_monthly' => [pow(1 + $monthlyRate, 1 / 2)    - 1, $termMonths * 2],
            default        => [$monthlyRate, $termMonths],
        };
    }

    /**
     * Validate that a computed schedule's principal column sums back to
     * the original principal (within a 1-centavo tolerance).
     */
    public function validateSchedule(array $schedule, int $principalCentavos): bool
    {
        $totalPrincipal = array_sum(array_column($schedule, 'principal_due'));
        return abs($totalPrincipal - $principalCentavos) <= 1;
    }
}
