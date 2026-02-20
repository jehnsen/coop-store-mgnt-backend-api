<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MemberSavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\TimeDeposit;
use App\Models\TimeDepositTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SavingsService
{
    // =========================================================================
    // MODULE 3A — MEMBER SAVINGS (VOLUNTARY + COMPULSORY)
    // =========================================================================

    /**
     * Open a new voluntary or compulsory savings account for a member.
     */
    public function openSavingsAccount(array $data): MemberSavingsAccount
    {
        $customer = Customer::where('uuid', $data['customer_uuid'])
            ->where('store_id', $data['store_id'])
            ->firstOrFail();

        if (! $customer->is_member) {
            throw new \RuntimeException('Only cooperative members can open a savings account.');
        }

        return DB::transaction(function () use ($data, $customer) {
            $account = MemberSavingsAccount::create([
                'store_id'       => $data['store_id'],
                'customer_id'    => $customer->id,
                'savings_type'   => $data['savings_type'] ?? 'voluntary',
                'interest_rate'  => $data['interest_rate'] ?? 0,
                'minimum_balance' => isset($data['minimum_balance'])
                    ? (int) round($data['minimum_balance'] * 100)
                    : 0,
                'status'         => 'active',
                'opened_date'    => $data['opened_date'] ?? now()->toDateString(),
                'notes'          => $data['notes'] ?? null,
            ]);



            return $account;
        });
    }

    /**
     * Record a deposit or compulsory deduction to a savings account.
     */
    public function deposit(
        MemberSavingsAccount $account,
        array                $data,
        User                 $operator
    ): SavingsTransaction {
        if ($account->status !== 'active') {
            throw new \RuntimeException('Deposits can only be made to active savings accounts.');
        }

        return DB::transaction(function () use ($account, $data, $operator) {
            $amountCentavos  = (int) round($data['amount'] * 100);
            $balanceBefore   = $account->getRawOriginal('current_balance');
            $balanceAfter    = $balanceBefore + $amountCentavos;

            $txType = $data['transaction_type'] ?? 'deposit';
            if (! in_array($txType, ['deposit', 'compulsory_deduction', 'adjustment'])) {
                $txType = 'deposit';
            }

            $transaction = SavingsTransaction::create([
                'store_id'           => $account->store_id,
                'customer_id'        => $account->customer_id,
                'savings_account_id' => $account->id,
                'user_id'            => $operator->id,
                'transaction_type'   => $txType,
                'amount'             => $amountCentavos,
                'balance_before'     => $balanceBefore,
                'balance_after'      => $balanceAfter,
                'payment_method'     => $data['payment_method'] ?? null,
                'reference_number'   => $data['reference_number'] ?? null,
                'transaction_date'   => $data['transaction_date'] ?? now()->toDateString(),
                'notes'              => $data['notes'] ?? null,
            ]);

            DB::table('member_savings_accounts')
                ->where('id', $account->id)
                ->update([
                    'current_balance'      => $balanceAfter,
                    'total_deposited'      => DB::raw("total_deposited + {$amountCentavos}"),
                    'last_transaction_date' => $transaction->transaction_date,
                ]);



            return $transaction;
        });
    }

    /**
     * Record a withdrawal from a savings account.
     */
    public function withdraw(
        MemberSavingsAccount $account,
        array                $data,
        User                 $operator
    ): SavingsTransaction {
        if ($account->status !== 'active') {
            throw new \RuntimeException('Withdrawals can only be made from active savings accounts.');
        }

        return DB::transaction(function () use ($account, $data, $operator) {
            $amountCentavos   = (int) round($data['amount'] * 100);
            $currentCentavos  = $account->getRawOriginal('current_balance');
            $minimumCentavos  = $account->getRawOriginal('minimum_balance');
            $availableCentavos = max(0, $currentCentavos - $minimumCentavos);

            if ($amountCentavos > $availableCentavos) {
                throw new \RuntimeException(sprintf(
                    'Withdrawal of ₱%s exceeds available balance of ₱%s (minimum maintaining balance: ₱%s).',
                    number_format($amountCentavos / 100, 2),
                    number_format($availableCentavos / 100, 2),
                    number_format($minimumCentavos / 100, 2),
                ));
            }

            $balanceBefore = $currentCentavos;
            $balanceAfter  = $currentCentavos - $amountCentavos;

            $transaction = SavingsTransaction::create([
                'store_id'           => $account->store_id,
                'customer_id'        => $account->customer_id,
                'savings_account_id' => $account->id,
                'user_id'            => $operator->id,
                'transaction_type'   => 'withdrawal',
                'amount'             => $amountCentavos,
                'balance_before'     => $balanceBefore,
                'balance_after'      => $balanceAfter,
                'payment_method'     => $data['payment_method'] ?? null,
                'reference_number'   => $data['reference_number'] ?? null,
                'transaction_date'   => $data['transaction_date'] ?? now()->toDateString(),
                'notes'              => $data['notes'] ?? null,
            ]);

            DB::table('member_savings_accounts')
                ->where('id', $account->id)
                ->update([
                    'current_balance'       => $balanceAfter,
                    'total_withdrawn'       => DB::raw("total_withdrawn + {$amountCentavos}"),
                    'last_transaction_date' => $transaction->transaction_date,
                ]);



            return $transaction;
        });
    }

    /**
     * Credit monthly savings interest to a single account.
     * interest = current_balance × annual_rate / 12  (simple monthly)
     */
    public function creditInterest(
        MemberSavingsAccount $account,
        array                $data,
        User                 $operator
    ): SavingsTransaction {
        if ($account->status !== 'active') {
            throw new \RuntimeException('Interest can only be credited to active savings accounts.');
        }

        return DB::transaction(function () use ($account, $data, $operator) {
            $balanceCentavos = $account->getRawOriginal('current_balance');
            $annualRate      = (float) $account->getRawOriginal('interest_rate') / 1_000_000; // stored as decimal(8,6)
            $interestCentavos = (int) round($balanceCentavos * $annualRate / 12);

            if ($interestCentavos <= 0) {
                throw new \RuntimeException('Computed interest is zero. Verify account interest rate.');
            }

            $balanceBefore = $balanceCentavos;
            $balanceAfter  = $balanceCentavos + $interestCentavos;

            $transaction = SavingsTransaction::create([
                'store_id'           => $account->store_id,
                'customer_id'        => $account->customer_id,
                'savings_account_id' => $account->id,
                'user_id'            => $operator->id,
                'transaction_type'   => 'interest_credit',
                'amount'             => $interestCentavos,
                'balance_before'     => $balanceBefore,
                'balance_after'      => $balanceAfter,
                'payment_method'     => null,
                'reference_number'   => $data['reference_number'] ?? null,
                'transaction_date'   => $data['transaction_date'] ?? now()->toDateString(),
                'notes'              => $data['notes'] ?? ($data['period_label'] ?? null),
            ]);

            DB::table('member_savings_accounts')
                ->where('id', $account->id)
                ->update([
                    'current_balance'       => $balanceAfter,
                    'total_interest_earned' => DB::raw("total_interest_earned + {$interestCentavos}"),
                    'last_transaction_date' => $transaction->transaction_date,
                ]);



            return $transaction;
        });
    }

    /**
     * Batch-credit monthly interest to all active voluntary savings accounts for a store.
     */
    public function batchCreditInterest(int $storeId, array $data, User $operator): array
    {
        $accounts = MemberSavingsAccount::where('store_id', $storeId)
            ->where('status', 'active')
            ->where('savings_type', 'voluntary')
            ->where('interest_rate', '>', 0)
            ->get();

        $credited       = 0;
        $totalInterest  = 0;
        $errors         = [];

        foreach ($accounts as $account) {
            try {
                $tx = $this->creditInterest($account, $data, $operator);
                $credited++;
                $totalInterest += $tx->getRawOriginal('amount');
            } catch (\Exception $e) {
                $errors[] = [
                    'account_number' => $account->account_number,
                    'error'          => $e->getMessage(),
                ];
            }
        }

        return [
            'accounts_credited'      => $credited,
            'total_interest_credited' => number_format($totalInterest / 100, 2, '.', ''),
            'errors'                 => $errors,
        ];
    }

    /**
     * Reverse a savings transaction (immutable ledger — marks is_reversed, restores balance).
     */
    public function reverseSavingsTransaction(
        SavingsTransaction   $transaction,
        User                 $operator
    ): SavingsTransaction {
        if ($transaction->is_reversed) {
            throw new \RuntimeException('This transaction has already been reversed.');
        }

        $account = $transaction->savingsAccount;

        return DB::transaction(function () use ($transaction, $account, $operator) {
            $amountCentavos  = $transaction->getRawOriginal('amount');
            $currentCentavos = $account->getRawOriginal('current_balance');

            // Determine net effect direction
            $inflows  = ['deposit', 'interest_credit', 'compulsory_deduction', 'adjustment'];
            $outflows = ['withdrawal', 'closing_payout'];

            if (in_array($transaction->transaction_type, $inflows)) {
                $newBalance = $currentCentavos - $amountCentavos;
                if ($newBalance < 0) {
                    throw new \RuntimeException('Reversing this transaction would result in a negative balance.');
                }
                $updates = [
                    'current_balance' => $newBalance,
                    'total_deposited' => DB::raw("total_deposited - {$amountCentavos}"),
                ];
                if ($transaction->transaction_type === 'interest_credit') {
                    $updates['total_interest_earned'] = DB::raw("total_interest_earned - {$amountCentavos}");
                    unset($updates['total_deposited']);
                }
            } else {
                $newBalance = $currentCentavos + $amountCentavos;
                $updates = [
                    'current_balance' => $newBalance,
                    'total_withdrawn' => DB::raw("total_withdrawn - {$amountCentavos}"),
                ];
            }

            $transaction->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $operator->id,
            ]);

            DB::table('member_savings_accounts')
                ->where('id', $account->id)
                ->update($updates);



            return $transaction->fresh();
        });
    }

    /**
     * Close a savings account. If balance > 0, a closing_payout transaction is created first.
     */
    public function closeSavingsAccount(
        MemberSavingsAccount $account,
        array                $data,
        User                 $operator
    ): MemberSavingsAccount {
        if ($account->status === 'closed') {
            throw new \RuntimeException('This savings account is already closed.');
        }

        return DB::transaction(function () use ($account, $data, $operator) {
            $balanceCentavos = $account->getRawOriginal('current_balance');

            if ($balanceCentavos > 0) {
                SavingsTransaction::create([
                    'store_id'           => $account->store_id,
                    'customer_id'        => $account->customer_id,
                    'savings_account_id' => $account->id,
                    'user_id'            => $operator->id,
                    'transaction_type'   => 'closing_payout',
                    'amount'             => $balanceCentavos,
                    'balance_before'     => $balanceCentavos,
                    'balance_after'      => 0,
                    'payment_method'     => $data['closing_payment_method'] ?? null,
                    'reference_number'   => $data['reference_number'] ?? null,
                    'transaction_date'   => $data['closed_date'] ?? now()->toDateString(),
                    'notes'              => $data['notes'] ?? 'Account closure payout.',
                ]);
            }

            DB::table('member_savings_accounts')
                ->where('id', $account->id)
                ->update([
                    'status'          => 'closed',
                    'closed_date'     => $data['closed_date'] ?? now()->toDateString(),
                    'closed_by'       => $operator->id,
                    'current_balance' => 0,
                ]);



            return $account->fresh();
        });
    }

    /**
     * Generate an account statement for a savings account over a date range.
     */
    public function getSavingsStatement(
        MemberSavingsAccount $account,
        Carbon               $from,
        Carbon               $to
    ): array {
        // Opening balance = sum of all non-reversed inflows minus outflows before $from
        $openingCentavos = $this->computeSavingsBalance($account->id, null, $from->copy()->subDay());

        $transactions = SavingsTransaction::where('savings_account_id', $account->id)
            ->where('is_reversed', false)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $closingCentavos = $account->getRawOriginal('current_balance');

        return [
            'account'          => $account->load('customer:id,uuid,name,member_id'),
            'period_from'      => $from->toDateString(),
            'period_to'        => $to->toDateString(),
            'opening_balance'  => number_format($openingCentavos / 100, 2, '.', ''),
            'transactions'     => $transactions,
            'closing_balance'  => number_format($closingCentavos / 100, 2, '.', ''),
            'total_deposits'   => number_format(
                $transactions->whereIn('transaction_type', ['deposit', 'compulsory_deduction', 'interest_credit'])
                    ->sum(fn ($t) => $t->getRawOriginal('amount')) / 100,
                2, '.', ''
            ),
            'total_withdrawals' => number_format(
                $transactions->whereIn('transaction_type', ['withdrawal', 'closing_payout'])
                    ->sum(fn ($t) => $t->getRawOriginal('amount')) / 100,
                2, '.', ''
            ),
        ];
    }

    /**
     * Portfolio overview for all savings accounts in a store.
     */
    public function getPortfolioOverview(int $storeId): array
    {
        $now   = now();
        $soM   = $now->copy()->startOfMonth();
        $eomD  = $now->copy()->endOfMonth();

        $accounts = MemberSavingsAccount::where('store_id', $storeId)->get();

        $voluntaryActive   = $accounts->where('status', 'active')->where('savings_type', 'voluntary');
        $compulsoryActive  = $accounts->where('status', 'active')->where('savings_type', 'compulsory');
        $dormant           = $accounts->where('status', 'dormant');
        $closed            = $accounts->where('status', 'closed');

        $totalBalance = MemberSavingsAccount::where('store_id', $storeId)
            ->where('status', 'active')
            ->sum('current_balance');

        $depositedThisMonth = SavingsTransaction::where('store_id', $storeId)
            ->whereIn('transaction_type', ['deposit', 'compulsory_deduction'])
            ->where('is_reversed', false)
            ->whereBetween('transaction_date', [$soM->toDateString(), $eomD->toDateString()])
            ->sum('amount');

        $withdrawnThisMonth = SavingsTransaction::where('store_id', $storeId)
            ->where('transaction_type', 'withdrawal')
            ->where('is_reversed', false)
            ->whereBetween('transaction_date', [$soM->toDateString(), $eomD->toDateString()])
            ->sum('amount');

        $interestYtd = SavingsTransaction::where('store_id', $storeId)
            ->where('transaction_type', 'interest_credit')
            ->where('is_reversed', false)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        return [
            'voluntary_active_count'   => $voluntaryActive->count(),
            'compulsory_active_count'  => $compulsoryActive->count(),
            'dormant_count'            => $dormant->count(),
            'closed_count'             => $closed->count(),
            'total_savings_balance'    => number_format($totalBalance / 100, 2, '.', ''),
            'deposited_this_month'     => number_format($depositedThisMonth / 100, 2, '.', ''),
            'withdrawn_this_month'     => number_format($withdrawnThisMonth / 100, 2, '.', ''),
            'interest_credited_ytd'    => number_format($interestYtd / 100, 2, '.', ''),
        ];
    }

    // =========================================================================
    // MODULE 3B — TIME DEPOSITS
    // =========================================================================

    /**
     * Place a new time deposit for a member.
     */
    public function placeTimeDeposit(array $data, User $operator): TimeDeposit
    {
        $customer = Customer::where('uuid', $data['customer_uuid'])
            ->where('store_id', $data['store_id'])
            ->firstOrFail();

        if (! $customer->is_member) {
            throw new \RuntimeException('Only cooperative members can place a time deposit.');
        }

        return DB::transaction(function () use ($data, $customer, $operator) {
            $principalCentavos = (int) round($data['principal_amount'] * 100);
            $annualRate        = (float) $data['interest_rate'];
            $termMonths        = (int) $data['term_months'];
            $placementDate     = Carbon::parse($data['placement_date']);
            $maturityDate      = $placementDate->copy()->addMonthsNoOverflow($termMonths);

            // Simple interest: P × R × T/12
            $expectedInterest = (int) round($principalCentavos * $annualRate * $termMonths / 12);

            $td = TimeDeposit::create([
                'store_id'                    => $data['store_id'],
                'customer_id'                 => $customer->id,
                'principal_amount'            => $principalCentavos,
                'interest_rate'               => $annualRate,
                'interest_method'             => $data['interest_method'] ?? 'simple_on_maturity',
                'payment_frequency'           => $data['payment_frequency'] ?? 'on_maturity',
                'term_months'                 => $termMonths,
                'early_withdrawal_penalty_rate' => $data['early_withdrawal_penalty_rate'] ?? 0.25,
                'placement_date'              => $placementDate->toDateString(),
                'maturity_date'               => $maturityDate->toDateString(),
                'current_balance'             => $principalCentavos,
                'total_interest_earned'       => 0,
                'expected_interest'           => $expectedInterest,
                'status'                      => 'active',
                'notes'                       => $data['notes'] ?? null,
            ]);

            TimeDepositTransaction::create([
                'store_id'          => $td->store_id,
                'customer_id'       => $td->customer_id,
                'time_deposit_id'   => $td->id,
                'user_id'           => $operator->id,
                'transaction_type'  => 'placement',
                'amount'            => $principalCentavos,
                'interest_amount'   => 0,
                'penalty_amount'    => 0,
                'balance_before'    => 0,
                'balance_after'     => $principalCentavos,
                'payment_method'    => $data['payment_method'] ?? null,
                'reference_number'  => $data['reference_number'] ?? null,
                'transaction_date'  => $placementDate->toDateString(),
                'notes'             => $data['notes'] ?? null,
            ]);



            return $td->load('customer:id,uuid,name,member_id');
        });
    }

    /**
     * Compute expected interest without saving (preview).
     */
    public function computeInterestPreview(array $data): array
    {
        $principalCentavos = (int) round($data['principal_amount'] * 100);
        $annualRate        = (float) $data['interest_rate'];
        $termMonths        = (int) $data['term_months'];
        $placementDate     = Carbon::parse($data['placement_date']);
        $maturityDate      = $placementDate->copy()->addMonthsNoOverflow($termMonths);

        $expectedInterest  = (int) round($principalCentavos * $annualRate * $termMonths / 12);
        $totalPayout       = $principalCentavos + $expectedInterest;

        return [
            'principal_amount'    => number_format($principalCentavos / 100, 2, '.', ''),
            'interest_rate_pct'   => round($annualRate * 100, 4),
            'term_months'         => $termMonths,
            'placement_date'      => $placementDate->toDateString(),
            'maturity_date'       => $maturityDate->toDateString(),
            'expected_interest'   => number_format($expectedInterest / 100, 2, '.', ''),
            'total_payout'        => number_format($totalPayout / 100, 2, '.', ''),
            'effective_yield_pct' => $principalCentavos > 0
                ? round(($expectedInterest / $principalCentavos) * 100, 4)
                : 0,
        ];
    }

    /**
     * Record a periodic interest accrual or payout for a time deposit.
     */
    public function accrueTimeDepositInterest(
        TimeDeposit $td,
        array       $data,
        User        $operator
    ): TimeDepositTransaction {
        if ($td->status !== 'active') {
            throw new \RuntimeException('Interest can only be accrued for active time deposits.');
        }

        if ($td->interest_method !== 'periodic' && $td->payment_frequency === 'on_maturity') {
            throw new \RuntimeException('This time deposit uses simple_on_maturity — use the mature endpoint instead.');
        }

        return DB::transaction(function () use ($td, $data, $operator) {
            $periodFrom  = Carbon::parse($data['period_from']);
            $periodTo    = Carbon::parse($data['period_to']);
            $periodDays  = (int) $periodFrom->diffInDays($periodTo) + 1;
            $annualRate  = (float) $td->getRawOriginal('interest_rate') / 1_000_000;
            $principal   = $td->getRawOriginal('principal_amount');

            // period_interest = principal × annual_rate × (period_days / 365)
            $interestCentavos = (int) round($principal * $annualRate * $periodDays / 365);

            $balanceBefore = $td->getRawOriginal('current_balance');
            $balanceAfter  = $balanceBefore + $interestCentavos;

            $tx = TimeDepositTransaction::create([
                'store_id'         => $td->store_id,
                'customer_id'      => $td->customer_id,
                'time_deposit_id'  => $td->id,
                'user_id'          => $operator->id,
                'transaction_type' => 'interest_accrual',
                'amount'           => $interestCentavos,
                'interest_amount'  => $interestCentavos,
                'penalty_amount'   => 0,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'period_from'      => $periodFrom->toDateString(),
                'period_to'        => $periodTo->toDateString(),
                'notes'            => $data['notes'] ?? null,
            ]);

            DB::table('time_deposits')
                ->where('id', $td->id)
                ->update([
                    'current_balance'       => $balanceAfter,
                    'total_interest_earned' => DB::raw("total_interest_earned + {$interestCentavos}"),
                ]);



            return $tx;
        });
    }

    /**
     * Process time deposit maturity payout.
     */
    public function matureTimeDeposit(
        TimeDeposit $td,
        array       $data,
        User        $operator
    ): TimeDepositTransaction {
        if ($td->status !== 'active') {
            throw new \RuntimeException('Only active time deposits can be matured.');
        }

        $maturityDate = Carbon::parse($td->maturity_date);
        $txDate       = Carbon::parse($data['transaction_date'] ?? now()->toDateString());

        if ($txDate->lt($maturityDate)) {
            throw new \RuntimeException(
                "Time deposit matures on {$maturityDate->toDateString()}. Use pre-terminate for early withdrawal."
            );
        }

        return DB::transaction(function () use ($td, $data, $operator, $txDate) {
            $principal        = $td->getRawOriginal('principal_amount');
            $annualRate       = (float) $td->getRawOriginal('interest_rate') / 1_000_000;
            $termMonths       = $td->term_months;
            $alreadyEarned    = $td->getRawOriginal('total_interest_earned');

            // For simple_on_maturity: compute total interest; for periodic, it's already accrued
            if ($td->interest_method === 'simple_on_maturity') {
                $totalInterest = (int) round($principal * $annualRate * $termMonths / 12);
                $newInterest   = $totalInterest - $alreadyEarned;
            } else {
                $newInterest = 0; // already accrued periodically
                $totalInterest = $alreadyEarned;
            }

            $balanceBefore = $td->getRawOriginal('current_balance');
            $finalBalance  = $balanceBefore + $newInterest;
            $payout        = $finalBalance;

            $tx = TimeDepositTransaction::create([
                'store_id'         => $td->store_id,
                'customer_id'      => $td->customer_id,
                'time_deposit_id'  => $td->id,
                'user_id'          => $operator->id,
                'transaction_type' => 'maturity_payout',
                'amount'           => $payout,
                'interest_amount'  => $newInterest,
                'penalty_amount'   => 0,
                'balance_before'   => $balanceBefore,
                'balance_after'    => 0,
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'transaction_date' => $txDate->toDateString(),
                'notes'            => $data['notes'] ?? 'Maturity payout.',
            ]);

            DB::table('time_deposits')
                ->where('id', $td->id)
                ->update([
                    'current_balance'       => 0,
                    'total_interest_earned' => $alreadyEarned + $newInterest,
                    'status'                => 'matured',
                    'matured_at'            => now(),
                ]);



            return $tx;
        });
    }

    /**
     * Pre-terminate a time deposit before maturity.
     */
    public function preTerminate(
        TimeDeposit $td,
        array       $data,
        User        $operator
    ): TimeDepositTransaction {
        if ($td->status !== 'active') {
            throw new \RuntimeException('Only active time deposits can be pre-terminated.');
        }

        $terminationDate = Carbon::parse($data['pre_termination_date']);
        $placementDate   = Carbon::parse($td->placement_date);

        if ($terminationDate->gt(Carbon::parse($td->maturity_date))) {
            throw new \RuntimeException('Termination date is after maturity. Use the mature endpoint.');
        }

        return DB::transaction(function () use ($td, $data, $operator, $terminationDate, $placementDate) {
            $principal   = $td->getRawOriginal('principal_amount');
            $annualRate  = (float) $td->getRawOriginal('interest_rate') / 1_000_000;
            $daysHeld    = (int) $placementDate->diffInDays($terminationDate);

            // Prorated simple interest for days actually held
            $earnedInterest = (int) round($principal * $annualRate * $daysHeld / 365);

            // Penalty = earned_interest × penalty_rate
            $penaltyRate    = (float) $td->getRawOriginal('early_withdrawal_penalty_rate') / 10_000;
            $penaltyCentavos = (int) round($earnedInterest * $penaltyRate);
            $netInterest     = $earnedInterest - $penaltyCentavos;
            $netPayout       = $principal + $netInterest;

            $balanceBefore = $td->getRawOriginal('current_balance');

            $tx = TimeDepositTransaction::create([
                'store_id'         => $td->store_id,
                'customer_id'      => $td->customer_id,
                'time_deposit_id'  => $td->id,
                'user_id'          => $operator->id,
                'transaction_type' => 'pre_termination',
                'amount'           => $netPayout,
                'interest_amount'  => $earnedInterest,
                'penalty_amount'   => $penaltyCentavos,
                'balance_before'   => $balanceBefore,
                'balance_after'    => 0,
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'transaction_date' => $terminationDate->toDateString(),
                'notes'            => $data['notes'] ?? null,
            ]);

            DB::table('time_deposits')
                ->where('id', $td->id)
                ->update([
                    'current_balance'         => 0,
                    'total_interest_earned'   => $earnedInterest,
                    'status'                  => 'pre_terminated',
                    'pre_terminated_at'       => now(),
                    'pre_terminated_by'       => $operator->id,
                    'pre_termination_reason'  => $data['reason'] ?? null,
                ]);



            return $tx;
        });
    }

    /**
     * Roll over a time deposit to a new one.
     */
    public function rollOver(TimeDeposit $td, array $data, User $operator): TimeDeposit
    {
        if (! in_array($td->status, ['active', 'matured'])) {
            throw new \RuntimeException('Only active or matured time deposits can be rolled over.');
        }

        return DB::transaction(function () use ($td, $data, $operator) {
            // Determine new principal: original OR principal + interest
            $originalPrincipal = $td->getRawOriginal('principal_amount');
            $earnedInterest    = $td->getRawOriginal('total_interest_earned');
            $rolloverPrincipal = ($data['include_interest'] ?? false)
                ? $originalPrincipal + $earnedInterest
                : $originalPrincipal;

            // Close current TD as rolled_over
            DB::table('time_deposits')
                ->where('id', $td->id)
                ->update(['status' => 'rolled_over']);

            // Record rollover transaction on old TD
            TimeDepositTransaction::create([
                'store_id'         => $td->store_id,
                'customer_id'      => $td->customer_id,
                'time_deposit_id'  => $td->id,
                'user_id'          => $operator->id,
                'transaction_type' => 'rollover',
                'amount'           => $rolloverPrincipal,
                'interest_amount'  => $earnedInterest,
                'penalty_amount'   => 0,
                'balance_before'   => $td->getRawOriginal('current_balance'),
                'balance_after'    => 0,
                'payment_method'   => null,
                'transaction_date' => $data['placement_date'] ?? now()->toDateString(),
                'notes'            => 'Rolled over to new time deposit.',
            ]);

            // Create new TD
            $newData = array_merge($data, [
                'store_id'           => $td->store_id,
                'customer_uuid'      => $td->customer->uuid,
                'principal_amount'   => $rolloverPrincipal / 100,  // service expects pesos
                'interest_rate'      => $data['interest_rate'] ?? (float) ($td->getRawOriginal('interest_rate') / 1_000_000),
                'term_months'        => $data['term_months'] ?? $td->term_months,
                'payment_frequency'  => $data['payment_frequency'] ?? $td->payment_frequency,
                'interest_method'    => $data['interest_method'] ?? $td->interest_method,
                'early_withdrawal_penalty_rate' => $data['early_withdrawal_penalty_rate'] ?? (float) ($td->getRawOriginal('early_withdrawal_penalty_rate') / 10_000),
                'placement_date'     => $data['placement_date'] ?? now()->toDateString(),
            ]);

            $newTd = $this->placeTimeDeposit($newData, $operator);

            // Link parent + increment rollover count
            DB::table('time_deposits')
                ->where('id', $newTd->id)
                ->update([
                    'parent_time_deposit_id' => $td->id,
                    'rollover_count'         => $td->rollover_count + 1,
                ]);



            return $newTd->fresh();
        });
    }

    /**
     * Get a statement for a single time deposit (all non-reversed transactions).
     */
    public function getTimeDepositStatement(TimeDeposit $td): array
    {
        $transactions = TimeDepositTransaction::where('time_deposit_id', $td->id)
            ->where('is_reversed', false)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return [
            'time_deposit'         => $td->load('customer:id,uuid,name,member_id'),
            'transactions'         => $transactions,
            'total_interest_earned' => number_format($td->getRawOriginal('total_interest_earned') / 100, 2, '.', ''),
            'current_value'        => number_format($td->getRawOriginal('current_balance') / 100, 2, '.', ''),
        ];
    }

    /**
     * Portfolio overview for all time deposits in a store.
     */
    public function getTimeDepositPortfolioOverview(int $storeId): array
    {
        $now         = now();
        $monthStart  = $now->copy()->startOfMonth()->toDateString();
        $monthEnd    = $now->copy()->endOfMonth()->toDateString();

        $totalActive         = TimeDeposit::where('store_id', $storeId)->where('status', 'active')->count();
        $totalPrincipal      = TimeDeposit::where('store_id', $storeId)->where('status', 'active')->sum('principal_amount');
        $totalCurrentValue   = TimeDeposit::where('store_id', $storeId)->where('status', 'active')->sum('current_balance');

        $maturingThisMonth   = TimeDeposit::where('store_id', $storeId)
            ->where('status', 'active')
            ->whereBetween('maturity_date', [$monthStart, $monthEnd])
            ->selectRaw('COUNT(*) as count, SUM(current_balance) as total')
            ->first();

        $maturedPending      = TimeDeposit::where('store_id', $storeId)
            ->where('status', 'matured')
            ->count();

        $preTerminatedYtd    = TimeDeposit::where('store_id', $storeId)
            ->where('status', 'pre_terminated')
            ->whereYear('pre_terminated_at', $now->year)
            ->count();

        $avgRate = TimeDeposit::where('store_id', $storeId)
            ->where('status', 'active')
            ->avg('interest_rate');

        return [
            'total_active_deposits'    => $totalActive,
            'total_principal_placed'   => number_format($totalPrincipal / 100, 2, '.', ''),
            'total_current_value'      => number_format($totalCurrentValue / 100, 2, '.', ''),
            'maturing_this_month'      => [
                'count'  => (int) ($maturingThisMonth->count ?? 0),
                'amount' => number_format(($maturingThisMonth->total ?? 0) / 100, 2, '.', ''),
            ],
            'matured_pending_payout'   => $maturedPending,
            'pre_terminated_ytd'       => $preTerminatedYtd,
            'average_interest_rate_pct' => round((float) $avgRate * 100, 4),
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Compute the savings account balance as of a given date by summing all non-reversed transactions.
     */
    private function computeSavingsBalance(int $accountId, ?string $from, Carbon $to): int
    {
        $inflows  = ['deposit', 'interest_credit', 'compulsory_deduction', 'adjustment'];
        $outflows = ['withdrawal', 'closing_payout'];

        $query = SavingsTransaction::where('savings_account_id', $accountId)
            ->where('is_reversed', false)
            ->where('transaction_date', '<=', $to->toDateString());

        if ($from) {
            $query->where('transaction_date', '>=', $from);
        }

        $txns = $query->get(['transaction_type', 'amount']);

        $balance = 0;
        foreach ($txns as $t) {
            $centavos = $t->getRawOriginal('amount');
            if (in_array($t->transaction_type, $inflows)) {
                $balance += $centavos;
            } elseif (in_array($t->transaction_type, $outflows)) {
                $balance -= $centavos;
            }
        }

        return max(0, $balance);
    }
}
