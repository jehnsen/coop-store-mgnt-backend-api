<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\MemberSavingsAccount;
use App\Models\MemberShareAccount;
use App\Models\PatronageRefundBatch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Provides factory-style helpers for cooperative finance feature tests.
 *
 * Usage: `use Tests\Support\CreatesFinancialFixtures;` in a test class.
 *
 * Monetary values follow the same convention as the API:
 *   - Input helpers accept PESOS (float/int) for readability.
 *   - Direct DB columns use CENTAVOS (int) where noted.
 */
trait CreatesFinancialFixtures
{
    // ─── Store & Users ────────────────────────────────────────────────────────

    protected function createStore(array $overrides = []): Store
    {
        return Store::create(array_merge([
            'uuid'               => Str::uuid(),
            'name'               => 'Test Cooperative ' . Str::random(6),
            'slug'               => 'test-coop-' . Str::random(6),
            'is_active'          => true,
            'vat_rate'           => 0,
            'vat_inclusive'      => false,
            'is_vat_registered'  => false,
            'currency'           => 'PHP',
        ], $overrides));
    }

    protected function createOperator(Store $store, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'store_id' => $store->id,
        ], $overrides));
    }

    // ─── Members ──────────────────────────────────────────────────────────────

    protected function createMember(Store $store, array $overrides = []): Customer
    {
        // BelongsToStore global scope filters by auth user's store_id.
        // We provide store_id explicitly so withoutGlobalScopes() isn't needed.
        return Customer::withoutGlobalScopes()->create(array_merge([
            'uuid'          => Str::uuid(),
            'store_id'      => $store->id,
            'name'          => 'Test Member ' . Str::random(5),
            'type'          => 'regular',
            'is_active'     => true,
            'is_member'     => true,
            'member_status' => 'active',
            'credit_limit'  => 0,
        ], $overrides));
    }

    protected function createNonMember(Store $store, array $overrides = []): Customer
    {
        return Customer::withoutGlobalScopes()->create(array_merge([
            'uuid'         => Str::uuid(),
            'store_id'     => $store->id,
            'name'         => 'Non-Member ' . Str::random(5),
            'type'         => 'walk_in',
            'is_active'    => true,
            'is_member'    => false,
            'credit_limit' => 0,
        ], $overrides));
    }

    // ─── Loan Products ────────────────────────────────────────────────────────

    /**
     * @param  float  $interestRate   Monthly rate (e.g. 0.015 = 1.5 %)
     * @param  float  $minAmountPesos
     * @param  float  $maxAmountPesos
     */
    protected function createLoanProduct(Store $store, array $overrides = []): LoanProduct
    {
        return LoanProduct::withoutGlobalScopes()->create(array_merge([
            'uuid'                => Str::uuid(),
            'store_id'            => $store->id,
            'code'                => 'LP-' . Str::random(4),
            'name'                => 'Regular Loan',
            'loan_type'           => 'regular',
            'interest_rate'       => 0.015,   // 1.5 %/month
            'interest_method'     => 'diminishing_balance',
            'max_term_months'     => 24,
            'min_amount'          => 100,     // ₱100 min (pesos — mutator converts to centavos)
            'max_amount'          => 100000,  // ₱100,000 max
            'processing_fee_rate' => 0.0,
            'service_fee'         => 0,
            'requires_collateral' => false,
            'is_active'           => true,
        ], $overrides));
    }

    // ─── Savings Accounts ─────────────────────────────────────────────────────

    /**
     * Create an active savings account for a member.
     * minimum_balance is in PESOS.
     */
    protected function createSavingsAccount(
        Store    $store,
        Customer $member,
        array    $overrides = []
    ): MemberSavingsAccount {
        return MemberSavingsAccount::withoutGlobalScopes()->create(array_merge([
            'uuid'             => Str::uuid(),
            'store_id'         => $store->id,
            'customer_id'      => $member->id,
            'savings_type'     => 'voluntary',
            'interest_rate'    => 0.04,       // 4 % annual (stored as decimal(8,6))
            'minimum_balance'  => 0,          // pesos — mutator converts
            'status'           => 'active',
            'opened_date'      => now()->toDateString(),
        ], $overrides));
    }

    // ─── Share Accounts ───────────────────────────────────────────────────────

    /**
     * Create an active share capital account.
     * par_value_per_share and subscribed_shares are raw DB values:
     *   par_value stored in centavos.
     */
    protected function createShareAccount(
        Store    $store,
        Customer $member,
        array    $overrides = []
    ): MemberShareAccount {
        $parValueCentavos = $overrides['par_value_per_share'] ?? 10000; // ₱100 in centavos
        $shares           = $overrides['subscribed_shares'] ?? 10;

        return MemberShareAccount::withoutGlobalScopes()->create(array_merge([
            'uuid'                    => Str::uuid(),
            'store_id'                => $store->id,
            'customer_id'             => $member->id,
            'share_type'              => 'regular',
            'subscribed_shares'       => $shares,
            'par_value_per_share'     => $parValueCentavos,
            'total_subscribed_amount' => $parValueCentavos * $shares,
            'total_paid_up_amount'    => 0,
            'status'                  => 'active',
            'opened_date'             => now()->toDateString(),
        ], $overrides));
    }

    // ─── Patronage Refund Batches ─────────────────────────────────────────────

    protected function createPatronageBatch(Store $store, array $overrides = []): PatronageRefundBatch
    {
        return PatronageRefundBatch::withoutGlobalScopes()->create(array_merge([
            'uuid'               => Str::uuid(),
            'store_id'           => $store->id,
            'period_label'       => '2025 Annual Patronage Refund',
            'period_from'        => '2025-01-01',
            'period_to'          => '2025-12-31',
            'computation_method' => 'rate_based',
            'pr_rate'            => 0.03,     // 3 % (stored as decimal(8,6))
            'pr_fund'            => 0,        // centavos, used for pool_based
            'status'             => 'draft',
            'member_count'       => 0,
        ], $overrides));
    }

    // ─── Completed Sales (for patronage refund computation) ───────────────────

    /**
     * Create a completed sale for a member.
     * total_amount is in CENTAVOS.
     */
    protected function createCompletedSale(
        Store    $store,
        Customer $member,
        int      $totalAmountCentavos,
        ?string  $createdAt = null
    ): Sale {
        return Sale::withoutGlobalScopes()->create([
            'uuid'         => Str::uuid(),
            'store_id'     => $store->id,
            'customer_id'  => $member->id,
            'sale_number'  => 'S-' . Str::random(8),
            'subtotal'     => $totalAmountCentavos,
            'total_amount' => $totalAmountCentavos,
            'status'       => 'completed',
            'created_at'   => $createdAt ?? now(),
            'updated_at'   => $createdAt ?? now(),
        ]);
    }
}
