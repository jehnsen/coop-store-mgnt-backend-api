<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MemberShareAccount;
use App\Models\ShareCapitalPayment;
use App\Models\ShareCertificate;
use App\Services\ShareCapitalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\CreatesFinancialFixtures;
use Tests\TestCase;

/**
 * Feature tests for ShareCapitalService.
 *
 * Covers:
 *   Open account, record payment, reverse payment,
 *   issue certificate, cancel certificate,
 *   share withdrawal (mid-year), ISC computation (weighted average).
 *
 * Monetary values for recordPayment() are in PESOS (service converts via * 100).
 * Direct DB fixture values (par_value_per_share, etc.) are in CENTAVOS.
 */
class ShareCapitalServiceTest extends TestCase
{
    use RefreshDatabase, CreatesFinancialFixtures;

    private ShareCapitalService $service;

    private $store;
    private $operator;
    private $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service  = new ShareCapitalService();
        $this->store    = $this->createStore();
        $this->operator = $this->createOperator($this->store);
        $this->member   = $this->createMember($this->store);

        $this->actingAs($this->operator);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Open a share account with 10 shares @ ₱100/share.
     * par_value_per_share provided in centavos (fixture level).
     */
    private function openAccount(?Customer $member = null, array $overrides = []): MemberShareAccount
    {
        $member = $member ?? $this->member;

        return $this->service->openShareAccount(array_merge([
            'customer_uuid'      => $member->uuid,
            'share_type'         => 'regular',
            'subscribed_shares'  => 10,
            'par_value_per_share' => 100.00, // ₱100/share (pesos — service converts)
            'opened_date'        => now()->toDateString(),
        ], $overrides));
    }

    // =========================================================================
    // Open Share Account
    // =========================================================================

    #[Test]
    public function open_share_account_creates_active_account(): void
    {
        $account = $this->openAccount();

        $this->assertDatabaseHas('member_share_accounts', [
            'id'     => $account->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function open_share_account_generates_account_number(): void
    {
        $account = $this->openAccount();

        $this->assertMatchesRegularExpression('/^SHA-\d{4}-\d{6}$/', $account->account_number);
    }

    #[Test]
    public function open_share_account_calculates_total_subscribed_amount(): void
    {
        // 10 shares × ₱100 = ₱1,000 → 100,000 centavos
        $account = $this->openAccount();

        $this->assertEquals(100_000, $account->getRawOriginal('total_subscribed_amount'));
    }

    #[Test]
    public function open_share_account_throws_for_non_member(): void
    {
        $nonMember = $this->createNonMember($this->store);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only cooperative members can open a share capital account');

        $this->service->openShareAccount([
            'customer_uuid'       => $nonMember->uuid,
            'share_type'          => 'regular',
            'subscribed_shares'   => 5,
            'par_value_per_share' => 100.00,
        ]);
    }

    // =========================================================================
    // Record Payment
    // =========================================================================

    #[Test]
    public function record_payment_increases_paid_up_amount(): void
    {
        $account = $this->openAccount(); // total_subscribed = ₱1,000

        $this->service->recordPayment($account, [
            'amount'         => 500.00, // ₱500 in pesos
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $account->refresh();
        $this->assertEquals(50_000, $account->getRawOriginal('total_paid_up_amount'));
    }

    #[Test]
    public function record_payment_creates_ledger_entry(): void
    {
        $account = $this->openAccount();

        $payment = $this->service->recordPayment($account, [
            'amount'         => 250.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $this->assertDatabaseHas('share_capital_payments', [
            'id'     => $payment->id,
            'amount' => 25_000, // centavos
        ]);
    }

    #[Test]
    public function record_payment_tracks_balance_before_and_after(): void
    {
        $account = $this->openAccount();

        // First payment ₱400
        $this->service->recordPayment($account, [
            'amount'         => 400.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        // Second payment ₱300
        $payment2 = $this->service->recordPayment($account, [
            'amount'         => 300.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $this->assertEquals(40_000, $payment2->getRawOriginal('balance_before'));
        $this->assertEquals(70_000, $payment2->getRawOriginal('balance_after'));
    }

    #[Test]
    public function record_payment_throws_when_exceeds_subscription(): void
    {
        $account = $this->openAccount(); // 10 shares × ₱100 = ₱1,000

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds remaining subscription');

        $this->service->recordPayment($account, [
            'amount'         => 1001.00, // ₱1,001 > ₱1,000 subscription
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
    }

    #[Test]
    public function record_payment_throws_for_non_active_account(): void
    {
        $account = $this->openAccount();
        $this->service->withdrawShares($account, [
            'withdrawn_date' => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot record a payment on a non-active share account');

        $this->service->recordPayment($account, [
            'amount'         => 100.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
    }

    // =========================================================================
    // Reverse Payment
    // =========================================================================

    #[Test]
    public function reverse_payment_decreases_paid_up_amount(): void
    {
        $account = $this->openAccount();
        $payment = $this->service->recordPayment($account, [
            'amount'         => 500.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $this->service->reversePayment($payment, $this->operator);

        $account->refresh();
        $this->assertEquals(0, $account->getRawOriginal('total_paid_up_amount'));
    }

    #[Test]
    public function reverse_payment_marks_is_reversed(): void
    {
        $account = $this->openAccount();
        $payment = $this->service->recordPayment($account, [
            'amount'         => 200.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $reversed = $this->service->reversePayment($payment, $this->operator);

        $this->assertTrue($reversed->is_reversed);
        $this->assertEquals($this->operator->id, $reversed->reversed_by);
    }

    #[Test]
    public function reverse_payment_throws_when_already_reversed(): void
    {
        $account = $this->openAccount();
        $payment = $this->service->recordPayment($account, [
            'amount'         => 200.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $this->service->reversePayment($payment, $this->operator);
        $payment->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This payment has already been reversed');

        $this->service->reversePayment($payment, $this->operator);
    }

    // =========================================================================
    // Certificate Issuance
    // =========================================================================

    #[Test]
    public function issue_certificate_creates_active_certificate(): void
    {
        $account = $this->openAccount();
        // Pay for all 10 shares: 10 × ₱100 = ₱1,000
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        $cert = $this->service->issueCertificate($account, [
            'shares_covered' => 10,
            'issue_date'     => now()->toDateString(),
        ], $this->operator);

        $this->assertEquals('active', $cert->status);
        $this->assertEquals(10, $cert->shares_covered);
    }

    #[Test]
    public function issue_certificate_stores_correct_face_value(): void
    {
        $account = $this->openAccount(); // par_value = ₱100 (10,000 centavos)
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        $cert = $this->service->issueCertificate($account, [
            'shares_covered' => 5,
            'issue_date'     => now()->toDateString(),
        ], $this->operator);

        // 5 shares × ₱100 = ₱500 = 50,000 centavos
        $this->assertEquals(50_000, $cert->getRawOriginal('face_value'));
    }

    #[Test]
    public function issue_certificate_throws_when_shares_exceed_paid_up(): void
    {
        $account = $this->openAccount(); // 10 shares total

        // Pay for only 3 shares (3 × ₱100 = ₱300)
        $this->service->recordPayment($account, [
            'amount'         => 300.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        // Try to issue certificate for 5 shares (but only 3 are paid)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot issue certificate for 5 shares');

        $this->service->issueCertificate($account, [
            'shares_covered' => 5,
            'issue_date'     => now()->toDateString(),
        ], $this->operator);
    }

    // =========================================================================
    // Cancel Certificate
    // =========================================================================

    #[Test]
    public function cancel_certificate_sets_status_to_cancelled(): void
    {
        $account = $this->openAccount();
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        $cert = $this->service->issueCertificate($account, [
            'shares_covered' => 10,
            'issue_date'     => now()->toDateString(),
        ], $this->operator);

        $cancelled = $this->service->cancelCertificate($cert, 'Member resigned.', $this->operator);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Member resigned.', $cancelled->cancellation_reason);
    }

    #[Test]
    public function cancel_certificate_throws_when_already_cancelled(): void
    {
        $account = $this->openAccount();
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        $cert = $this->service->issueCertificate($account, [
            'shares_covered' => 10,
            'issue_date'     => now()->toDateString(),
        ], $this->operator);

        $this->service->cancelCertificate($cert, 'First cancel.', $this->operator);
        $cert->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This certificate is already cancelled');

        $this->service->cancelCertificate($cert, 'Duplicate cancel.', $this->operator);
    }

    // =========================================================================
    // Share Withdrawal (Mid-Year)
    // =========================================================================

    #[Test]
    public function withdraw_shares_sets_account_status_to_withdrawn(): void
    {
        $account = $this->openAccount();

        $withdrawn = $this->service->withdrawShares($account, [
            'withdrawn_date' => now()->toDateString(),
        ], $this->operator);

        $this->assertEquals('withdrawn', $withdrawn->status);
        $this->assertDatabaseHas('member_share_accounts', [
            'id'     => $account->id,
            'status' => 'withdrawn',
        ]);
    }

    #[Test]
    public function withdraw_shares_cancels_all_active_certificates(): void
    {
        $account = $this->openAccount();
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
        $account->refresh();

        // Issue two certificates
        $this->service->issueCertificate($account, ['shares_covered' => 5, 'issue_date' => now()->toDateString()], $this->operator);
        $this->service->issueCertificate($account, ['shares_covered' => 3, 'issue_date' => now()->toDateString()], $this->operator);

        $this->service->withdrawShares($account, ['withdrawn_date' => now()->toDateString()], $this->operator);

        // All certificates should be cancelled
        $activeCerts = ShareCertificate::where('share_account_id', $account->id)
            ->where('status', 'active')
            ->count();

        $this->assertEquals(0, $activeCerts);
    }

    #[Test]
    public function withdraw_shares_throws_when_already_withdrawn(): void
    {
        $account = $this->openAccount();
        $this->service->withdrawShares($account, ['withdrawn_date' => now()->toDateString()], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This share account has already been withdrawn');

        $this->service->withdrawShares($account, ['withdrawn_date' => now()->toDateString()], $this->operator);
    }

    // =========================================================================
    // ISC Computation — Weighted Daily Average
    // =========================================================================

    #[Test]
    public function compute_isc_returns_correct_structure(): void
    {
        $account = $this->openAccount();
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => '2025-01-15',
        ], $this->operator);

        $result = $this->service->computeISC($this->store->id, 2025, 0.12);

        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('dividend_rate', $result);
        $this->assertArrayHasKey('total_members', $result);
        $this->assertArrayHasKey('total_isc_declared', $result);
        $this->assertArrayHasKey('members', $result);
    }

    #[Test]
    public function compute_isc_weighted_average_full_year_payment(): void
    {
        // Member pays ₱1,000 on Jan 1 — held all year (365 days)
        $account = $this->openAccount();
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => '2025-01-01',
        ], $this->operator);

        $result = $this->service->computeISC($this->store->id, 2025, 0.12);

        // Average daily balance ≈ ₱1,000 (held full year)
        // ISC = ₱1,000 × 12 % = ₱120
        $member = $result['members'][0];
        $this->assertEqualsWithDelta(1000.00, (float) $member['average_paid_up'], 1.0);
        $this->assertEqualsWithDelta(120.00,  (float) $member['isc_amount'],     1.0);
    }

    #[Test]
    public function compute_isc_weighted_average_mid_year_payment(): void
    {
        // Member pays ₱1,000 on Jul 1 (≈ 184 days held out of 365)
        $account = $this->openAccount();
        $this->service->recordPayment($account, [
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => '2025-07-01',
        ], $this->operator);

        $result = $this->service->computeISC($this->store->id, 2025, 0.12);

        // Average daily balance ≈ ₱1,000 × (184/365) ≈ ₱504
        // ISC ≈ ₱504 × 12 % ≈ ₱60.48
        $member = $result['members'][0];
        $this->assertGreaterThan(0, (float) $member['average_paid_up']);
        $this->assertLessThan(1000.00, (float) $member['average_paid_up'],
            'Mid-year payment should result in less than full-year average');

        // ISC should be proportionally less than full-year ISC
        $this->assertLessThan(120.00, (float) $member['isc_amount']);
        $this->assertGreaterThan(0, (float) $member['isc_amount']);
    }

    #[Test]
    public function compute_isc_aggregates_multiple_payments_in_year(): void
    {
        // Member makes two payments during the year
        $account = $this->openAccount();

        // ₱500 on Jan 1
        $this->service->recordPayment($account, [
            'amount'         => 500.00,
            'payment_method' => 'cash',
            'payment_date'   => '2025-01-01',
        ], $this->operator);

        // ₱500 more on Jul 1
        $this->service->recordPayment($account, [
            'amount'         => 500.00,
            'payment_method' => 'cash',
            'payment_date'   => '2025-07-01',
        ], $this->operator);

        $result = $this->service->computeISC($this->store->id, 2025, 0.12);

        // Average should be between ₱500 (only first payment all year) and ₱1,000 (both payments all year)
        $member = $result['members'][0];
        $this->assertGreaterThan(500.0, (float) $member['average_paid_up']);
        $this->assertLessThan(1000.0, (float) $member['average_paid_up']);
    }

    #[Test]
    public function compute_isc_grand_total_equals_sum_of_members(): void
    {
        // Create two members, each with different payment histories
        $member2   = $this->createMember($this->store);
        $account1  = $this->openAccount($this->member);
        $account2  = $this->openAccount($member2);

        $this->service->recordPayment($account1, [
            'amount' => 1000.00, 'payment_method' => 'cash', 'payment_date' => '2025-01-01',
        ], $this->operator);

        $this->service->recordPayment($account2, [
            'amount' => 2000.00, 'payment_method' => 'cash', 'payment_date' => '2025-01-01',
        ], $this->operator);

        $result = $this->service->computeISC($this->store->id, 2025, 0.12);

        $memberSum = array_sum(array_column($result['members'], 'isc_amount'));
        $this->assertEqualsWithDelta(
            $result['total_isc_declared'],
            $memberSum,
            0.02, // allow 2 centavo rounding
            'Grand total ISC must equal sum of member ISC amounts'
        );
    }
}
