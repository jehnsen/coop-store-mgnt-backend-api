<?php

namespace Tests\Unit;

use App\Services\AmortizationService;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for AmortizationService.
 *
 * This service is pure computation — no database writes.
 * All monetary values are in CENTAVOS.
 */
class AmortizationServiceTest extends TestCase
{
    private AmortizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmortizationService();
    }

    // =========================================================================
    // Monthly Schedule
    // =========================================================================

    #[Test]
    public function monthly_schedule_generates_correct_number_of_periods(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,   // ₱10,000
            monthlyRate:        0.015,       // 1.5 %/month
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
            interval:           'monthly',
        );

        $this->assertCount(12, $result['schedule']);
    }

    #[Test]
    public function monthly_schedule_beginning_balance_equals_principal(): void
    {
        $principal = 1_000_000; // ₱10,000 in centavos

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        $this->assertEquals($principal, $result['schedule'][0]['beginning_balance']);
    }

    #[Test]
    public function monthly_schedule_ending_balance_hits_zero(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        $lastRow = end($result['schedule']);
        $this->assertEquals(0, $lastRow['ending_balance']);
    }

    #[Test]
    public function monthly_schedule_total_payable_equals_principal_plus_interest(): void
    {
        $principal = 1_000_000;

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        $this->assertEquals(
            $principal + $result['total_interest'],
            $result['total_payable']
        );
    }

    #[Test]
    public function monthly_schedule_passes_principal_validation(): void
    {
        $principal = 1_000_000;

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        $this->assertTrue(
            $this->service->validateSchedule($result['schedule'], $principal)
        );
    }

    #[Test]
    public function monthly_schedule_due_dates_are_one_month_apart(): void
    {
        $firstPayment = Carbon::parse('2026-02-01');

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         6,
            firstPaymentDate:   $firstPayment,
            interval:           'monthly',
        );

        $schedule = $result['schedule'];
        for ($i = 1; $i < count($schedule); $i++) {
            $prev = Carbon::parse($schedule[$i - 1]['due_date']);
            $curr = Carbon::parse($schedule[$i]['due_date']);
            $this->assertEquals(1, $prev->diffInMonths($curr),
                "Period {$i}: expected 1-month gap between payments");
        }
    }

    #[Test]
    public function monthly_schedule_each_row_has_required_keys(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 500_000,
            monthlyRate:        0.01,
            termMonths:         3,
            firstPaymentDate:   Carbon::parse('2026-03-01'),
        );

        $requiredKeys = [
            'payment_number', 'due_date', 'beginning_balance',
            'principal_due', 'interest_due', 'total_due', 'ending_balance',
        ];

        foreach ($result['schedule'] as $row) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $row, "Missing key: {$key}");
            }
        }
    }

    #[Test]
    public function monthly_schedule_each_row_total_due_equals_principal_plus_interest(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        foreach ($result['schedule'] as $row) {
            $this->assertEquals(
                $row['principal_due'] + $row['interest_due'],
                $row['total_due'],
                "Row {$row['payment_number']}: total_due mismatch"
            );
        }
    }

    #[Test]
    public function monthly_schedule_balance_decreases_each_period(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        $schedule = $result['schedule'];
        for ($i = 1; $i < count($schedule); $i++) {
            $this->assertLessThan(
                $schedule[$i - 1]['beginning_balance'],
                $schedule[$i]['beginning_balance'],
                "Balance should decrease each period"
            );
        }
    }

    // =========================================================================
    // Weekly Schedule
    // =========================================================================

    #[Test]
    public function weekly_schedule_generates_four_times_the_monthly_periods(): void
    {
        $termMonths = 6;

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         $termMonths,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
            interval:           'weekly',
        );

        // 6 months × 4 periods/month = 24 periods
        $this->assertCount($termMonths * 4, $result['schedule']);
    }

    #[Test]
    public function weekly_schedule_passes_principal_validation(): void
    {
        $principal = 2_000_000; // ₱20,000

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-07'),
            interval:           'weekly',
        );

        $this->assertTrue(
            $this->service->validateSchedule($result['schedule'], $principal)
        );
    }

    #[Test]
    public function weekly_schedule_ending_balance_hits_zero(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 500_000,
            monthlyRate:        0.015,
            termMonths:         3,
            firstPaymentDate:   Carbon::parse('2026-02-07'),
            interval:           'weekly',
        );

        $lastRow = end($result['schedule']);
        $this->assertEquals(0, $lastRow['ending_balance']);
    }

    #[Test]
    public function weekly_schedule_due_dates_are_one_week_apart(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 500_000,
            monthlyRate:        0.01,
            termMonths:         3,
            firstPaymentDate:   Carbon::parse('2026-02-07'),
            interval:           'weekly',
        );

        $schedule = $result['schedule'];
        for ($i = 1; $i < count($schedule); $i++) {
            $prev = Carbon::parse($schedule[$i - 1]['due_date']);
            $curr = Carbon::parse($schedule[$i]['due_date']);
            $this->assertEquals(7, $prev->diffInDays($curr),
                "Period {$i}: expected 7-day gap between payments");
        }
    }

    // =========================================================================
    // Semi-Monthly Schedule
    // =========================================================================

    #[Test]
    public function semi_monthly_schedule_generates_two_times_the_monthly_periods(): void
    {
        $termMonths = 6;

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         $termMonths,
            firstPaymentDate:   Carbon::parse('2026-02-15'),
            interval:           'semi_monthly',
        );

        // 6 months × 2 periods/month = 12 periods
        $this->assertCount($termMonths * 2, $result['schedule']);
    }

    #[Test]
    public function semi_monthly_schedule_passes_principal_validation(): void
    {
        $principal = 3_000_000; // ₱30,000

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-15'),
            interval:           'semi_monthly',
        );

        $this->assertTrue(
            $this->service->validateSchedule($result['schedule'], $principal)
        );
    }

    #[Test]
    public function semi_monthly_schedule_due_dates_are_15_days_apart(): void
    {
        $result = $this->service->computeDiminishingBalance(
            principalCentavos: 500_000,
            monthlyRate:        0.01,
            termMonths:         3,
            firstPaymentDate:   Carbon::parse('2026-02-15'),
            interval:           'semi_monthly',
        );

        $schedule = $result['schedule'];
        for ($i = 1; $i < count($schedule); $i++) {
            $prev = Carbon::parse($schedule[$i - 1]['due_date']);
            $curr = Carbon::parse($schedule[$i]['due_date']);
            $this->assertEquals(15, $prev->diffInDays($curr),
                "Period {$i}: expected 15-day gap between payments");
        }
    }

    // =========================================================================
    // Edge Cases — Rounding & Single Period
    // =========================================================================

    #[Test]
    public function last_period_absorbs_rounding_remainder(): void
    {
        // Use a principal that does not divide evenly into equal payments
        $principal = 100_001; // centavos (odd amount)

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );

        // After absorbing remainder, ending_balance must be exactly 0
        $lastRow = end($result['schedule']);
        $this->assertEquals(0, $lastRow['ending_balance'],
            'Last period must absorb rounding remainder so balance hits exactly 0');
    }

    #[Test]
    public function single_period_loan_pays_off_in_one_payment(): void
    {
        $principal = 500_000; // ₱5,000

        $result = $this->service->computeDiminishingBalance(
            principalCentavos: $principal,
            monthlyRate:        0.015,
            termMonths:         1,
            firstPaymentDate:   Carbon::parse('2026-03-01'),
        );

        $this->assertCount(1, $result['schedule']);
        $this->assertEquals(0, $result['schedule'][0]['ending_balance']);
        $this->assertEquals($principal, $result['schedule'][0]['principal_due']);
    }

    #[Test]
    public function validate_schedule_returns_false_when_principal_sum_is_off(): void
    {
        $schedule = [
            ['principal_due' => 100_000],
            ['principal_due' => 100_000],
        ];

        // Total = 200_000, but we claim principal is 300_000 → should fail
        $this->assertFalse($this->service->validateSchedule($schedule, 300_000));
    }

    #[Test]
    public function validate_schedule_allows_one_centavo_tolerance(): void
    {
        $schedule = [
            ['principal_due' => 333_333],
            ['principal_due' => 333_333],
            ['principal_due' => 333_334], // last row absorbs 1 centavo rounding
        ];

        // Sum = 1_000_000, principal = 1_000_000 → exact match
        $this->assertTrue($this->service->validateSchedule($schedule, 1_000_000));

        // Off by exactly 1 centavo → still valid
        $this->assertTrue($this->service->validateSchedule($schedule, 1_000_001));

        // Off by 2 centavos → invalid
        $this->assertFalse($this->service->validateSchedule($schedule, 999_998));
    }

    // =========================================================================
    // Validation — Invalid Inputs
    // =========================================================================

    #[Test]
    public function throws_when_principal_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Principal must be greater than zero');

        $this->service->computeDiminishingBalance(
            principalCentavos: 0,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );
    }

    #[Test]
    public function throws_when_principal_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->computeDiminishingBalance(
            principalCentavos: -1_000_000,
            monthlyRate:        0.015,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );
    }

    #[Test]
    public function throws_when_monthly_rate_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Monthly rate must be greater than zero');

        $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.0,
            termMonths:         12,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );
    }

    #[Test]
    public function throws_when_term_months_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Term months must be greater than zero');

        $this->service->computeDiminishingBalance(
            principalCentavos: 1_000_000,
            monthlyRate:        0.015,
            termMonths:         0,
            firstPaymentDate:   Carbon::parse('2026-02-01'),
        );
    }

    // =========================================================================
    // EMI Computation
    // =========================================================================

    #[Test]
    public function compute_emi_formula_is_correct(): void
    {
        // EMI = P × [r(1+r)^n] / [(1+r)^n − 1]
        // For P=1,000,000 (centavos), r=0.015, n=12:
        //   EMI ≈ 91_684 centavos (≈ ₱916.84)
        $emi = $this->service->computeEMI(1_000_000, 0.015, 12);

        // EMI should be positive and roughly equal to expected value
        $this->assertGreaterThan(0, $emi);
        // Allow ±100 centavos for rounding differences
        $this->assertEqualsWithDelta(91_684, $emi, 100);
    }

    // =========================================================================
    // getDueDates
    // =========================================================================

    #[Test]
    public function get_due_dates_returns_correct_count(): void
    {
        $dates = $this->service->getDueDates(Carbon::parse('2026-02-01'), 6, 'monthly');

        $this->assertCount(6, $dates);
        $this->assertInstanceOf(Carbon::class, $dates[0]);
    }

    #[Test]
    public function get_due_dates_first_date_matches_input(): void
    {
        $first = Carbon::parse('2026-03-15');
        $dates = $this->service->getDueDates($first, 3, 'monthly');

        $this->assertEquals('2026-03-15', $dates[0]->toDateString());
    }
}
