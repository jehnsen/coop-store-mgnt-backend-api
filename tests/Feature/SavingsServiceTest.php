<?php

namespace Tests\Feature;

use App\Models\MemberSavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\CreatesFinancialFixtures;
use Tests\TestCase;

/**
 * Feature tests for SavingsService (member savings module).
 *
 * Covers:
 *   Open account, deposit, withdrawal, interest credit,
 *   batch interest atomicity, reversal, account closure.
 *
 * SavingsService accepts monetary values in PESOS (converted to centavos internally).
 */
class SavingsServiceTest extends TestCase
{
    use RefreshDatabase, CreatesFinancialFixtures;

    private SavingsService $service;

    private $store;
    private $operator;
    private $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service  = new SavingsService();
        $this->store    = $this->createStore();
        $this->operator = $this->createOperator($this->store);
        $this->member   = $this->createMember($this->store);

        $this->actingAs($this->operator);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Open a voluntary account with optional minimum balance (pesos).
     */
    private function openAccount(float $minimumBalance = 0, float $interestRate = 0.04): MemberSavingsAccount
    {
        return $this->service->openSavingsAccount([
            'store_id'        => $this->store->id,
            'customer_uuid'   => $this->member->uuid,
            'savings_type'    => 'voluntary',
            'interest_rate'   => $interestRate,
            'minimum_balance' => $minimumBalance,
            'opened_date'     => now()->toDateString(),
        ]);
    }

    // =========================================================================
    // Open Account
    // =========================================================================

    #[Test]
    public function open_savings_account_creates_active_account(): void
    {
        $account = $this->openAccount();

        $this->assertDatabaseHas('member_savings_accounts', [
            'id'     => $account->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function open_savings_account_generates_unique_account_number(): void
    {
        $account = $this->openAccount();

        $this->assertMatchesRegularExpression('/^SVA-\d{4}-\d{6}$/', $account->account_number);
    }

    #[Test]
    public function open_savings_account_throws_for_non_member(): void
    {
        $nonMember = $this->createNonMember($this->store);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only cooperative members can open a savings account');

        $this->service->openSavingsAccount([
            'store_id'      => $this->store->id,
            'customer_uuid' => $nonMember->uuid,
            'savings_type'  => 'voluntary',
        ]);
    }

    #[Test]
    public function open_account_stores_minimum_balance_in_centavos(): void
    {
        $account = $this->openAccount(minimumBalance: 500.00); // ₱500

        // minimum_balance is stored in centavos via the mutator
        $this->assertEquals(50_000, $account->getRawOriginal('minimum_balance'));
    }

    // =========================================================================
    // Deposit
    // =========================================================================

    #[Test]
    public function deposit_increases_account_balance(): void
    {
        $account = $this->openAccount();

        $this->service->deposit($account, [
            'amount'         => 1000.00, // ₱1,000
            'payment_method' => 'cash',
        ], $this->operator);

        $account->refresh();
        $this->assertEquals(100_000, $account->getRawOriginal('current_balance'));
    }

    #[Test]
    public function deposit_creates_transaction_ledger_entry(): void
    {
        $account = $this->openAccount();

        $tx = $this->service->deposit($account, [
            'amount'         => 500.00,
            'payment_method' => 'cash',
        ], $this->operator);

        $this->assertDatabaseHas('savings_transactions', [
            'id'               => $tx->id,
            'transaction_type' => 'deposit',
            'amount'           => 50_000, // centavos
        ]);
    }

    #[Test]
    public function deposit_tracks_balance_before_and_after(): void
    {
        $account = $this->openAccount();

        // First deposit: 0 → 1000
        $this->service->deposit($account, ['amount' => 1000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Second deposit: 1000 → 1500
        $tx = $this->service->deposit($account, ['amount' => 500.00, 'payment_method' => 'cash'], $this->operator);

        $this->assertEquals(100_000, $tx->getRawOriginal('balance_before'));
        $this->assertEquals(150_000, $tx->getRawOriginal('balance_after'));
    }

    #[Test]
    public function deposit_throws_for_inactive_account(): void
    {
        $account = $this->openAccount();
        $this->service->closeSavingsAccount($account, [], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deposits can only be made to active savings accounts');

        $this->service->deposit($account, ['amount' => 100.00, 'payment_method' => 'cash'], $this->operator);
    }

    #[Test]
    public function deposit_increments_total_deposited(): void
    {
        $account = $this->openAccount();

        $this->service->deposit($account, ['amount' => 2000.00, 'payment_method' => 'cash'], $this->operator);
        $this->service->deposit($account, ['amount' => 500.00,  'payment_method' => 'cash'], $this->operator);

        $account->refresh();
        $this->assertEquals(250_000, $account->getRawOriginal('total_deposited'));
    }

    // =========================================================================
    // Withdrawal
    // =========================================================================

    #[Test]
    public function withdraw_decreases_account_balance(): void
    {
        $account = $this->openAccount();
        $this->service->deposit($account, ['amount' => 2000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $this->service->withdraw($account, [
            'amount'         => 500.00,
            'payment_method' => 'cash',
        ], $this->operator);

        $account->refresh();
        $this->assertEquals(150_000, $account->getRawOriginal('current_balance'));
    }

    #[Test]
    public function withdraw_creates_withdrawal_transaction(): void
    {
        $account = $this->openAccount();
        $this->service->deposit($account, ['amount' => 1000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $tx = $this->service->withdraw($account, [
            'amount'         => 300.00,
            'payment_method' => 'cash',
        ], $this->operator);

        $this->assertEquals('withdrawal', $tx->transaction_type);
        $this->assertEquals(30_000, $tx->getRawOriginal('amount'));
    }

    #[Test]
    public function withdraw_respects_minimum_maintaining_balance(): void
    {
        $account = $this->openAccount(minimumBalance: 200.00); // ₱200 minimum
        $this->service->deposit($account, ['amount' => 1000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Available = ₱1,000 - ₱200 minimum = ₱800
        // Attempting to withdraw ₱900 (exceeds available)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds available balance');

        $this->service->withdraw($account, [
            'amount'         => 900.00,
            'payment_method' => 'cash',
        ], $this->operator);
    }

    #[Test]
    public function withdraw_allows_withdrawal_up_to_available_balance(): void
    {
        $account = $this->openAccount(minimumBalance: 200.00); // ₱200 minimum
        $this->service->deposit($account, ['amount' => 1000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Withdraw exactly available (₱1,000 - ₱200 = ₱800) — should succeed
        $tx = $this->service->withdraw($account, [
            'amount'         => 800.00,
            'payment_method' => 'cash',
        ], $this->operator);

        $this->assertEquals(80_000, $tx->getRawOriginal('amount'));
    }

    #[Test]
    public function withdraw_throws_for_inactive_account(): void
    {
        $account = $this->openAccount();
        $this->service->closeSavingsAccount($account, [], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Withdrawals can only be made from active savings accounts');

        $this->service->withdraw($account, ['amount' => 100.00, 'payment_method' => 'cash'], $this->operator);
    }

    // =========================================================================
    // Interest Credit
    // =========================================================================

    #[Test]
    public function credit_interest_increases_balance(): void
    {
        $account = $this->openAccount(interestRate: 0.04); // 4 % annual
        $this->service->deposit($account, ['amount' => 12_000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Monthly interest = 12,000 * 0.04 / 12 = ₱40/month = 4,000 centavos
        $this->service->creditInterest($account, [
            'transaction_date' => now()->toDateString(),
            'period_label'     => 'January 2026',
        ], $this->operator);

        $account->refresh();
        $this->assertGreaterThan(1_200_000, $account->getRawOriginal('current_balance'));
    }

    #[Test]
    public function credit_interest_computes_simple_monthly_interest(): void
    {
        $account = $this->openAccount(interestRate: 0.12); // 12 % annual
        $this->service->deposit($account, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Monthly = 1,000,000 centavos * 0.12 / 12 = 10,000 centavos = ₱100
        $tx = $this->service->creditInterest($account, [
            'transaction_date' => now()->toDateString(),
        ], $this->operator);

        $this->assertEquals(10_000, $tx->getRawOriginal('amount'));
        $this->assertEquals('interest_credit', $tx->transaction_type);
    }

    #[Test]
    public function credit_interest_increments_total_interest_earned(): void
    {
        $account = $this->openAccount(interestRate: 0.12);
        $this->service->deposit($account, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $this->service->creditInterest($account, ['transaction_date' => now()->toDateString()], $this->operator);

        $account->refresh();
        $this->assertGreaterThan(0, $account->getRawOriginal('total_interest_earned'));
    }

    #[Test]
    public function credit_interest_throws_when_rate_is_zero(): void
    {
        $account = $this->openAccount(interestRate: 0.0); // zero rate
        $this->service->deposit($account, ['amount' => 5000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Computed interest is zero');

        $this->service->creditInterest($account, ['transaction_date' => now()->toDateString()], $this->operator);
    }

    #[Test]
    public function credit_interest_throws_for_inactive_account(): void
    {
        $account = $this->openAccount(interestRate: 0.12);
        $this->service->closeSavingsAccount($account, [], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Interest can only be credited to active savings accounts');

        $this->service->creditInterest($account, ['transaction_date' => now()->toDateString()], $this->operator);
    }

    // =========================================================================
    // Batch Interest Credit — Atomicity
    // =========================================================================

    #[Test]
    public function batch_credit_interest_credits_all_eligible_accounts(): void
    {
        // Create 3 members with savings accounts
        $members = collect([
            $this->createMember($this->store),
            $this->createMember($this->store),
            $this->createMember($this->store),
        ]);

        foreach ($members as $member) {
            $account = $this->service->openSavingsAccount([
                'store_id'      => $this->store->id,
                'customer_uuid' => $member->uuid,
                'savings_type'  => 'voluntary',
                'interest_rate' => 0.12,
            ]);
            $this->service->deposit($account, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);
        }

        $result = $this->service->batchCreditInterest($this->store->id, [
            'transaction_date' => now()->toDateString(),
            'period_label'     => 'Jan 2026',
        ], $this->operator);

        $this->assertEquals(3, $result['accounts_credited']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function batch_credit_interest_is_atomic_and_rolls_back_all_on_error(): void
    {
        // Account 1: valid (has balance + rate)
        $member1  = $this->createMember($this->store);
        $account1 = $this->service->openSavingsAccount([
            'store_id'      => $this->store->id,
            'customer_uuid' => $member1->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.12,
        ]);
        $this->service->deposit($account1, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);

        // Account 2: valid
        $member2  = $this->createMember($this->store);
        $account2 = $this->service->openSavingsAccount([
            'store_id'      => $this->store->id,
            'customer_uuid' => $member2->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.12,
        ]);
        $this->service->deposit($account2, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);

        // Account 3: rate is 0 — will cause creditInterest() to throw
        $member3  = $this->createMember($this->store);
        $account3 = $this->service->openSavingsAccount([
            'store_id'      => $this->store->id,
            'customer_uuid' => $member3->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.0, // will cause zero-interest error
        ]);
        $this->service->deposit($account3, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);

        // Batch MUST roll back all changes when even one account fails
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Batch interest credit failed');

        $this->service->batchCreditInterest($this->store->id, [
            'transaction_date' => now()->toDateString(),
        ], $this->operator);

        // After exception, no interest transactions should exist
        $interestTxCount = SavingsTransaction::where('transaction_type', 'interest_credit')->count();
        $this->assertEquals(0, $interestTxCount, 'All interest credits must be rolled back');
    }

    #[Test]
    public function batch_credit_interest_skips_zero_rate_accounts_in_filter(): void
    {
        // Account with rate = 0 should not be included in the batch query
        // (query filters `interest_rate > 0`)
        $member1  = $this->createMember($this->store);
        $account1 = $this->service->openSavingsAccount([
            'store_id'      => $this->store->id,
            'customer_uuid' => $member1->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.12,
        ]);
        $this->service->deposit($account1, ['amount' => 10_000.00, 'payment_method' => 'cash'], $this->operator);

        // Account with zero rate
        $member2  = $this->createMember($this->store);
        $this->service->openSavingsAccount([
            'store_id'      => $this->store->id,
            'customer_uuid' => $member2->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.0,
        ]);

        $result = $this->service->batchCreditInterest($this->store->id, [
            'transaction_date' => now()->toDateString(),
        ], $this->operator);

        // Only the account with a positive rate should be credited
        $this->assertEquals(1, $result['accounts_credited']);
    }

    // =========================================================================
    // Reverse Transaction
    // =========================================================================

    #[Test]
    public function reverse_deposit_decreases_balance_back(): void
    {
        $account = $this->openAccount();
        $tx = $this->service->deposit($account, ['amount' => 1000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $this->service->reverseSavingsTransaction($tx, $this->operator);

        $account->refresh();
        $this->assertEquals(0, $account->getRawOriginal('current_balance'));
    }

    #[Test]
    public function reverse_withdrawal_restores_balance(): void
    {
        $account = $this->openAccount();
        $this->service->deposit($account, ['amount' => 2000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $withdrawalTx = $this->service->withdraw($account, [
            'amount'         => 500.00,
            'payment_method' => 'cash',
        ], $this->operator);
        $account->refresh();

        $this->assertEquals(150_000, $account->getRawOriginal('current_balance'));

        $this->service->reverseSavingsTransaction($withdrawalTx, $this->operator);

        $account->refresh();
        $this->assertEquals(200_000, $account->getRawOriginal('current_balance'));
    }

    #[Test]
    public function reverse_transaction_marks_is_reversed_flag(): void
    {
        $account = $this->openAccount();
        $tx = $this->service->deposit($account, ['amount' => 500.00, 'payment_method' => 'cash'], $this->operator);

        $reversed = $this->service->reverseSavingsTransaction($tx, $this->operator);

        $this->assertTrue($reversed->is_reversed);
        $this->assertNotNull($reversed->reversed_at);
        $this->assertEquals($this->operator->id, $reversed->reversed_by);
    }

    #[Test]
    public function reverse_transaction_throws_when_already_reversed(): void
    {
        $account = $this->openAccount();
        $tx = $this->service->deposit($account, ['amount' => 500.00, 'payment_method' => 'cash'], $this->operator);

        $this->service->reverseSavingsTransaction($tx, $this->operator);
        $tx->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This transaction has already been reversed');

        $this->service->reverseSavingsTransaction($tx, $this->operator);
    }

    #[Test]
    public function reverse_deposit_throws_when_it_would_cause_negative_balance(): void
    {
        $account = $this->openAccount();
        $tx1 = $this->service->deposit($account, ['amount' => 1000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Withdraw most of the balance
        $this->service->withdraw($account, ['amount' => 900.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        // Reversing tx1 (₱1,000 deposit) would result in balance 100 - 1000 = -900 (negative)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('would result in a negative balance');

        $this->service->reverseSavingsTransaction($tx1, $this->operator);
    }

    // =========================================================================
    // Close Account
    // =========================================================================

    #[Test]
    public function close_account_creates_closing_payout_when_balance_positive(): void
    {
        $account = $this->openAccount();
        $this->service->deposit($account, ['amount' => 5000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $this->service->closeSavingsAccount($account, [
            'closing_payment_method' => 'cash',
            'closed_date'            => now()->toDateString(),
        ], $this->operator);

        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'transaction_type'   => 'closing_payout',
        ]);
    }

    #[Test]
    public function close_account_sets_balance_to_zero(): void
    {
        $account = $this->openAccount();
        $this->service->deposit($account, ['amount' => 3000.00, 'payment_method' => 'cash'], $this->operator);
        $account->refresh();

        $this->service->closeSavingsAccount($account, [], $this->operator);

        $account->refresh();
        $this->assertEquals(0, $account->getRawOriginal('current_balance'));
        $this->assertEquals('closed', $account->status);
    }

    #[Test]
    public function close_account_with_zero_balance_does_not_create_closing_payout(): void
    {
        $account = $this->openAccount(); // balance = 0

        $this->service->closeSavingsAccount($account, [], $this->operator);

        $payoutCount = SavingsTransaction::where('savings_account_id', $account->id)
            ->where('transaction_type', 'closing_payout')
            ->count();

        $this->assertEquals(0, $payoutCount);
    }

    #[Test]
    public function close_account_throws_when_already_closed(): void
    {
        $account = $this->openAccount();
        $this->service->closeSavingsAccount($account, [], $this->operator);
        $account->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This savings account is already closed');

        $this->service->closeSavingsAccount($account, [], $this->operator);
    }
}
