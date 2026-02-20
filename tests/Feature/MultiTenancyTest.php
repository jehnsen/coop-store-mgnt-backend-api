<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\MemberSavingsAccount;
use App\Models\MemberShareAccount;
use App\Services\AmortizationService;
use App\Services\LoanService;
use App\Services\SavingsService;
use App\Services\ShareCapitalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesFinancialFixtures;
use Tests\TestCase;

/**
 * Multi-tenancy isolation tests.
 *
 * Verifies that the BelongsToStore global scope correctly prevents
 * cross-cooperative data leakage. Each authenticated user should only
 * see and operate on records belonging to their own store.
 *
 * Pattern:
 *   1. Create Store A and Store B with independent users and data.
 *   2. Authenticate as User A → assert Store B's data is invisible.
 *   3. Authenticate as User B → assert Store A's data is invisible.
 */
class MultiTenancyTest extends TestCase
{
    use RefreshDatabase, CreatesFinancialFixtures;

    private $storeA;
    private $storeB;
    private $userA;
    private $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeA = $this->createStore();
        $this->storeB = $this->createStore();

        $this->userA = $this->createOperator($this->storeA);
        $this->userB = $this->createOperator($this->storeB);
    }

    // =========================================================================
    // Customer / Member isolation
    // =========================================================================

    #[Test]
    public function store_a_customers_are_not_visible_to_store_b_user(): void
    {
        $memberA = $this->createMember($this->storeA);

        // Authenticate as Store B user — should see 0 customers from Store A
        $this->actingAs($this->userB);

        $visible = Customer::all();
        $this->assertCount(0, $visible,
            'Store B user must not see Store A customers via default scope');

        $ids = $visible->pluck('id');
        $this->assertNotContains($memberA->id, $ids);
    }

    #[Test]
    public function store_b_customers_are_not_visible_to_store_a_user(): void
    {
        $memberB = $this->createMember($this->storeB);

        $this->actingAs($this->userA);

        $visible = Customer::all();
        $this->assertCount(0, $visible);
        $this->assertNotContains($memberB->id, $visible->pluck('id'));
    }

    #[Test]
    public function each_store_user_only_sees_own_customers(): void
    {
        $memberA1 = $this->createMember($this->storeA);
        $memberA2 = $this->createMember($this->storeA);
        $memberB1 = $this->createMember($this->storeB);

        // User A sees only Store A's customers
        $this->actingAs($this->userA);
        $visibleA = Customer::all();
        $this->assertCount(2, $visibleA);
        $this->assertContains($memberA1->id, $visibleA->pluck('id'));
        $this->assertContains($memberA2->id, $visibleA->pluck('id'));
        $this->assertNotContains($memberB1->id, $visibleA->pluck('id'));

        // User B sees only Store B's customers
        $this->actingAs($this->userB);
        $visibleB = Customer::all();
        $this->assertCount(1, $visibleB);
        $this->assertContains($memberB1->id, $visibleB->pluck('id'));
        $this->assertNotContains($memberA1->id, $visibleB->pluck('id'));
    }

    // =========================================================================
    // Loan isolation
    // =========================================================================

    #[Test]
    public function store_a_loans_are_not_visible_to_store_b_user(): void
    {
        $memberA     = $this->createMember($this->storeA);
        $productA    = $this->createLoanProduct($this->storeA);
        $loanService = new LoanService(new AmortizationService());

        $this->actingAs($this->userA);

        $loanA = $loanService->applyLoan([
            'customer_uuid'      => $memberA->uuid,
            'loan_product_uuid'  => $productA->uuid,
            'principal_amount'   => 1_000_000,
            'term_months'        => 6,
            'payment_interval'   => 'monthly',
            'purpose'            => 'Business',
            'first_payment_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ], $this->userA);

        // Switch to User B — loan must be invisible
        $this->actingAs($this->userB);

        $visibleLoans = Loan::all();
        $this->assertCount(0, $visibleLoans,
            'Store B user must not see Store A loans via default scope');

        $this->assertNotContains($loanA->id, $visibleLoans->pluck('id'));
    }

    #[Test]
    public function loan_products_are_isolated_per_store(): void
    {
        $productA = $this->createLoanProduct($this->storeA, ['code' => 'PLA-001']);
        $productB = $this->createLoanProduct($this->storeB, ['code' => 'PLB-001']);

        $this->actingAs($this->userA);
        $visibleA = LoanProduct::all();
        $this->assertCount(1, $visibleA);
        $this->assertEquals('PLA-001', $visibleA->first()->code);

        $this->actingAs($this->userB);
        $visibleB = LoanProduct::all();
        $this->assertCount(1, $visibleB);
        $this->assertEquals('PLB-001', $visibleB->first()->code);
    }

    // =========================================================================
    // Savings isolation
    // =========================================================================

    #[Test]
    public function store_a_savings_accounts_are_not_visible_to_store_b_user(): void
    {
        $memberA  = $this->createMember($this->storeA);
        $savings  = new SavingsService();

        $this->actingAs($this->userA);

        $savings->openSavingsAccount([
            'store_id'      => $this->storeA->id,
            'customer_uuid' => $memberA->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.04,
        ]);

        $this->actingAs($this->userB);

        $visibleAccounts = MemberSavingsAccount::all();
        $this->assertCount(0, $visibleAccounts,
            'Store B user must not see Store A savings accounts');
    }

    #[Test]
    public function savings_balance_updates_stay_within_own_store(): void
    {
        $memberA = $this->createMember($this->storeA);
        $memberB = $this->createMember($this->storeB);
        $savings = new SavingsService();

        // Open and deposit in Store A
        $this->actingAs($this->userA);
        $accountA = $savings->openSavingsAccount([
            'store_id'      => $this->storeA->id,
            'customer_uuid' => $memberA->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.04,
        ]);
        $savings->deposit($accountA, ['amount' => 5000.00, 'payment_method' => 'cash'], $this->userA);

        // Open account in Store B (should be independent)
        $this->actingAs($this->userB);
        $accountB = $savings->openSavingsAccount([
            'store_id'      => $this->storeB->id,
            'customer_uuid' => $memberB->uuid,
            'savings_type'  => 'voluntary',
            'interest_rate' => 0.04,
        ]);

        $accountA->refresh();
        $accountB->refresh();

        // Store A balance is unchanged
        $this->assertEquals(500_000, $accountA->getRawOriginal('current_balance'));
        // Store B balance is zero
        $this->assertEquals(0, $accountB->getRawOriginal('current_balance'));
    }

    // =========================================================================
    // Share capital isolation
    // =========================================================================

    #[Test]
    public function store_a_share_accounts_are_not_visible_to_store_b_user(): void
    {
        $memberA      = $this->createMember($this->storeA);
        $shareService = new ShareCapitalService();

        $this->actingAs($this->userA);

        $shareService->openShareAccount([
            'customer_uuid'       => $memberA->uuid,
            'share_type'          => 'regular',
            'subscribed_shares'   => 10,
            'par_value_per_share' => 100.00,
        ]);

        $this->actingAs($this->userB);

        $visibleAccounts = MemberShareAccount::all();
        $this->assertCount(0, $visibleAccounts,
            'Store B user must not see Store A share accounts');
    }

    // =========================================================================
    // withoutGlobalScopes — bypass check (admin cross-store view)
    // =========================================================================

    #[Test]
    public function without_global_scopes_returns_all_store_records(): void
    {
        $this->createMember($this->storeA);
        $this->createMember($this->storeA);
        $this->createMember($this->storeB);

        // Authenticated as User A — normally sees only 2 customers
        $this->actingAs($this->userA);

        $withScope    = Customer::all()->count();
        $withoutScope = Customer::withoutGlobalScopes()->count();

        $this->assertEquals(2, $withScope);
        $this->assertEquals(3, $withoutScope,
            'withoutGlobalScopes() must bypass store filter and return ALL records');
    }

    // =========================================================================
    // Model auto-assignment of store_id on create
    // =========================================================================

    #[Test]
    public function new_records_auto_assigned_store_id_from_authenticated_user(): void
    {
        $this->actingAs($this->userA);

        // Create a member without explicitly setting store_id
        $member = Customer::create([
            'uuid'          => \Illuminate\Support\Str::uuid(),
            'name'          => 'Auto-assigned Member',
            'type'          => 'regular',
            'is_active'     => true,
            'is_member'     => true,
            'member_status' => 'active',
            'credit_limit'  => 0,
        ]);

        $this->assertEquals(
            $this->storeA->id,
            $member->store_id,
            'BelongsToStore::creating() must auto-assign store_id from authenticated user'
        );
    }

    // =========================================================================
    // Cross-store service operations (should not affect other store's data)
    // =========================================================================

    #[Test]
    public function loan_payment_recorded_in_store_a_does_not_affect_store_b_totals(): void
    {
        $memberA     = $this->createMember($this->storeA);
        $memberB     = $this->createMember($this->storeB);
        $productA    = $this->createLoanProduct($this->storeA);
        $productB    = $this->createLoanProduct($this->storeB);
        $loanService = new LoanService(new AmortizationService());

        // Store A: apply and activate loan
        $this->actingAs($this->userA);
        $loanA = $loanService->applyLoan([
            'customer_uuid'      => $memberA->uuid,
            'loan_product_uuid'  => $productA->uuid,
            'principal_amount'   => 1_000_000,
            'term_months'        => 1,
            'payment_interval'   => 'monthly',
            'purpose'            => 'Test',
            'first_payment_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ], $this->userA);
        $loanService->approveLoan($loanA, $this->userA);
        $loanA->refresh();
        $loanService->disburseLoan($loanA, $this->userA, ['disbursement_date' => now()->toDateString()]);
        $loanA->refresh();

        // Store B: apply and activate a separate loan
        $this->actingAs($this->userB);
        $loanB = $loanService->applyLoan([
            'customer_uuid'      => $memberB->uuid,
            'loan_product_uuid'  => $productB->uuid,
            'principal_amount'   => 2_000_000,
            'term_months'        => 1,
            'payment_interval'   => 'monthly',
            'purpose'            => 'Test',
            'first_payment_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ], $this->userB);

        $balanceBBefore = $loanB->getRawOriginal('outstanding_balance');

        // Store A makes a payment — should only affect Store A's loan
        $this->actingAs($this->userA);
        $schedule = \App\Models\LoanAmortizationSchedule::where('loan_id', $loanA->id)->first();
        $loanService->recordPayment($loanA, [
            'amount'         => $schedule->getRawOriginal('total_due'),
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->userA);

        // Store B loan balance must be unchanged
        $loanB->refresh();
        $this->assertEquals($balanceBBefore, $loanB->getRawOriginal('outstanding_balance'),
            'Store A payment must not affect Store B loan balances');
    }
}
