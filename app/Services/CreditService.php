<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\WalletRestrictionException;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CreditTransaction;
use App\Models\Sale;
use App\Models\Store;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CustomerWalletRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreditService
{
    public function __construct(
        protected CustomerRepositoryInterface            $customerRepo,
        protected CreditTransactionRepositoryInterface   $creditTransactionRepo,
        protected SaleRepositoryInterface                $saleRepo,
        protected CustomerWalletRepositoryInterface      $walletRepo,   // MPC addition
    ) {
    }

    // =========================================================================
    // MPC: Wallet-based restricted spending
    // =========================================================================

    /**
     * Validate that every item in a cart is covered by the given wallet's
     * category whitelist.
     *
     * Iterates over $cartItems, resolves each product from the pre-loaded
     * $products collection (keyed by UUID), and checks whether the product's
     * category_id exists in $wallet->allowed_category_ids.
     *
     * @param  CustomerWallet           $wallet      The wallet being charged.
     * @param  array                    $cartItems   Raw items array from the
     *                                               sale request (each element
     *                                               must have 'product_id' = UUID).
     * @param  Collection               $products    Eloquent Collection of Product
     *                                               models, keyed by UUID.
     *                                               Must be loaded before calling
     *                                               this method to avoid N+1.
     *
     * @throws WalletRestrictionException When any item's category is not
     *                                    whitelisted in the wallet.
     */
    public function validateWalletUsage(
        CustomerWallet $wallet,
        array          $cartItems,
        Collection     $products,
    ): void {
        $allowedCategoryIds = $wallet->allowed_category_ids ?? [];

        foreach ($cartItems as $item) {
            $product = $products[$item['product_id']] ?? null;

            if ($product === null) {
                // Guard against stale cart data; SaleService validates existence
                // earlier, so this branch is a safety net only.
                continue;
            }

            if (!in_array($product->category_id, $allowedCategoryIds, strict: true)) {
                // Attempt to surface a human-readable category name when the
                // relationship is already loaded; fall back to the raw ID.
                $categoryName = $product->relationLoaded('category')
                    ? ($product->category?->name ?? "id:{$product->category_id}")
                    : "id:{$product->category_id}";

                throw new WalletRestrictionException(
                    walletName:   $wallet->name,
                    productName:  $product->name,
                    categoryName: $categoryName,
                    categoryId:   (int) $product->category_id,
                );
            }
        }
    }

    /**
     * Charge a specific wallet for a sale.
     *
     * Responsibilities:
     *   1. Guard that the wallet has sufficient balance.
     *   2. Decrement wallet balance via repository (raw centavo arithmetic).
     *   3. Increment customer total_outstanding.
     *   4. Write a CreditTransaction record linked to both the wallet and sale.
     *
     * @param  CustomerWallet $wallet  The wallet to debit.
     * @param  int            $amount  Amount in CENTAVOS.
     * @param  Sale           $sale    The sale that triggered the charge.
     *
     * @throws ValidationException When wallet balance is insufficient.
     */
    public function payWithWallet(
        CustomerWallet $wallet,
        int            $amount,
        Sale           $sale,
    ): CreditTransaction {
        return DB::transaction(function () use ($wallet, $amount, $sale) {

            // -- 1. Sufficient balance check (raw centavos) -------------------
            $walletBalanceBefore = (int) $wallet->getRawOriginal('balance');

            if ($walletBalanceBefore < $amount) {
                throw ValidationException::withMessages([
                    'payments' => [sprintf(
                        'Insufficient balance in wallet "%s". Available: ₱%s, Required: ₱%s.',
                        $wallet->name,
                        number_format($walletBalanceBefore / 100, 2),
                        number_format($amount / 100, 2),
                    )],
                ]);
            }

            // -- 2. Deduct wallet balance (raw decrement, bypasses mutator) ---
            $this->walletRepo->deductBalance($wallet->id, $amount);

            // -- 3. Update customer total_outstanding -------------------------
            $customer           = $wallet->customer;
            $customerBalBefore  = (int) ($customer->getRawOriginal('total_outstanding') ?? 0);
            $customerBalAfter   = $customerBalBefore + $amount;

            $this->customerRepo->update($customer->id, [
                'total_outstanding' => $customerBalAfter,
            ]);

            // -- 4. Write ledger entry ----------------------------------------
            $transaction = $this->creditTransactionRepo->create([
                'store_id'         => $customer->store_id,
                'customer_id'      => $customer->id,
                'wallet_id'        => $wallet->id,
                'sale_id'          => $sale->id,
                'user_id'          => auth()->id(),
                'type'             => 'charge',
                'reference_number' => $sale->sale_number,
                'amount'           => $amount,
                'balance_before'   => $customerBalBefore,
                'balance_after'    => $customerBalAfter,
                'description'      => "Wallet \"{$wallet->name}\" charge – {$sale->sale_number}",
                'transaction_date' => now(),
                'payment_method'   => 'wallet',
                'notes'            => "Paid via wallet: {$wallet->name}",
                'is_reversed'      => false,
            ]);



            return $this->creditTransactionRepo
                ->with(['customer', 'sale', 'wallet'])
                ->find($transaction->id);
        });
    }

    /**
     * Restore a wallet balance when a wallet-charged sale is voided or refunded.
     *
     * @param  CustomerWallet $wallet         Wallet to credit back.
     * @param  int            $amountCentavos Amount to restore, in centavos.
     * @param  Sale           $sale           The originating sale.
     * @param  string         $reason         Void / refund reason for audit trail.
     */
    public function reverseWalletCharge(
        CustomerWallet $wallet,
        int            $amountCentavos,
        Sale           $sale,
        string         $reason,
    ): void {
        DB::transaction(function () use ($wallet, $amountCentavos, $sale, $reason) {
            $this->walletRepo->restoreBalance($wallet->id, $amountCentavos);

            $customer          = $wallet->customer;
            $customerBalBefore = (int) ($customer->getRawOriginal('total_outstanding') ?? 0);
            $customerBalAfter  = max(0, $customerBalBefore - $amountCentavos);

            $this->customerRepo->update($customer->id, [
                'total_outstanding' => $customerBalAfter,
            ]);

            $this->creditTransactionRepo->create([
                'store_id'         => $customer->store_id,
                'customer_id'      => $customer->id,
                'wallet_id'        => $wallet->id,
                'sale_id'          => $sale->id,
                'user_id'          => auth()->id(),
                'type'             => 'reversal',
                'reference_number' => 'REV-' . $sale->sale_number,
                'amount'           => -$amountCentavos,
                'balance_before'   => $customerBalBefore,
                'balance_after'    => $customerBalAfter,
                'description'      => "Reversal of wallet \"{$wallet->name}\" – {$reason}",
                'transaction_date' => now(),
                'payment_method'   => 'wallet',
                'is_reversed'      => false,
            ]);
        });
    }

    // =========================================================================
    // Legacy single-credit-limit operations (retained for backwards compat)
    // =========================================================================

    /**
     * Charge credit to a customer's account (legacy, non-wallet flow).
     *
     * @param  Customer $customer
     * @param  Sale     $sale
     * @param  int      $amount    Amount in centavos
     * @param  int      $termsDays
     */
    public function chargeCredit(
        Customer $customer,
        Sale     $sale,
        int      $amount,
        int      $termsDays,
    ): CreditTransaction {
        return DB::transaction(function () use ($customer, $sale, $amount, $termsDays) {
            $balanceBefore = (int) ($customer->getRawOriginal('total_outstanding') ?? 0);
            $balanceAfter  = $balanceBefore + $amount;
            $dueDate       = now()->addDays($termsDays);

            $transaction = $this->creditTransactionRepo->create([
                'store_id'         => $customer->store_id,
                'customer_id'      => $customer->id,
                'sale_id'          => $sale->id,
                'user_id'          => auth()->id(),
                'type'             => 'charge',
                'reference_number' => $sale->sale_number,
                'amount'           => $amount,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'description'      => "Credit sale – {$sale->sale_number}",
                'transaction_date' => now(),
                'due_date'         => $dueDate,
                'payment_method'   => null,
                'notes'            => "Credit sale with {$termsDays} days terms",
                'is_reversed'      => false,
            ]);

            $this->customerRepo->update($customer->id, [
                'total_outstanding' => $balanceAfter,
            ]);



            return $this->creditTransactionRepo->with(['sale', 'customer'])->find($transaction->id);
        });
    }

    /**
     * Receive payment from customer (FIFO allocation).
     *
     * @param  Customer     $customer
     * @param  int          $amount        Amount in centavos
     * @param  string       $method
     * @param  string|null  $reference
     * @param  array|null   $invoiceUuids  Specific invoices to pay (FIFO if null)
     * @param  string|null  $notes
     * @return array{transaction: CreditTransaction, applied_to: array, remaining_credit: float}
     */
    public function receivePayment(
        Customer  $customer,
        int       $amount,
        string    $method,
        ?string   $reference    = null,
        ?array    $invoiceUuids = null,
        ?string   $notes        = null,
    ): array {
        return DB::transaction(function () use ($customer, $amount, $method, $reference, $invoiceUuids, $notes) {
            $balanceBefore = (int) ($customer->getRawOriginal('total_outstanding') ?? 0);
            $balanceAfter  = max(0, $balanceBefore - $amount);

            $transaction = $this->creditTransactionRepo->create([
                'store_id'         => $customer->store_id,
                'customer_id'      => $customer->id,
                'user_id'          => auth()->id(),
                'type'             => 'payment',
                'reference_number' => $reference ?? 'PAY-' . now()->format('YmdHis'),
                'amount'           => -$amount,   // negative = payment reducing balance
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'description'      => "Payment received – {$method}",
                'transaction_date' => now(),
                'payment_method'   => $method,
                'notes'            => $notes,
                'is_reversed'      => false,
            ]);

            // FIFO allocation to unpaid sales
            $appliedTo       = [];
            $remainingAmount = $amount;
            $sales           = $this->saleRepo->getUnpaidByCustomer($customer->id, $invoiceUuids);

            foreach ($sales as $sale) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $totalAmountCentavos  = (int) $sale->getRawOriginal('total_amount');
                $amountPaidCentavos   = (int) ($sale->getRawOriginal('amount_paid') ?? 0);
                $outstandingCentavos  = $totalAmountCentavos - $amountPaidCentavos;

                if ($outstandingCentavos <= 0) {
                    continue;
                }

                $amountToApply  = min($remainingAmount, $outstandingCentavos);
                $newAmountPaid  = $amountPaidCentavos + $amountToApply;

                $this->saleRepo->update($sale->id, [
                    'amount_paid'    => $newAmountPaid,
                    'payment_status' => $newAmountPaid >= $totalAmountCentavos ? 'paid' : 'partial',
                ]);

                $paidDate = $newAmountPaid >= $totalAmountCentavos ? now() : null;
                $this->creditTransactionRepo->updatePaidDateForSale($sale->id, $paidDate);

                $appliedTo[] = [
                    'sale_uuid'        => $sale->uuid,
                    'sale_number'      => $sale->sale_number,
                    'amount_applied'   => $amountToApply / 100,
                    'previous_balance' => $outstandingCentavos / 100,
                    'new_balance'      => max(0, $outstandingCentavos - $amountToApply) / 100,
                    'status'           => $newAmountPaid >= $totalAmountCentavos ? 'paid' : 'partial',
                ];

                $remainingAmount -= $amountToApply;
            }

            $this->updateOutstandingBalance($customer);



            return [
                'transaction'      => $this->creditTransactionRepo->with(['customer'])->find($transaction->id),
                'applied_to'       => $appliedTo,
                'remaining_credit' => $remainingAmount / 100,
            ];
        });
    }

    /**
     * Get aging report for all customers with outstanding balances.
     */
    public function getAgingReport(Store $store): array
    {
        $customers = $this->customerRepo->getWithOutstanding()
            ->map(function ($customer) {
                $transactions = $this->creditTransactionRepo->getUnpaidInvoices($customer->id);

                $aging = [
                    'current' => 0,
                    '31_60'   => 0,
                    '61_90'   => 0,
                    'over_90' => 0,
                ];

                $oldestDays = 0;

                foreach ($transactions as $transaction) {
                    if (!$transaction->due_date) {
                        continue;
                    }

                    $daysOverdue  = now()->diffInDays($transaction->due_date, false);
                    $amountInPesos = $transaction->getRawOriginal('amount') / 100;

                    if ($daysOverdue <= 30) {
                        $aging['current'] += $amountInPesos;
                    } elseif ($daysOverdue <= 60) {
                        $aging['31_60'] += $amountInPesos;
                    } elseif ($daysOverdue <= 90) {
                        $aging['61_90'] += $amountInPesos;
                    } else {
                        $aging['over_90'] += $amountInPesos;
                    }

                    $oldestDays = max($oldestDays, $daysOverdue);
                }

                $customer->aging_current    = $aging['current'];
                $customer->aging_31_60      = $aging['31_60'];
                $customer->aging_61_90      = $aging['61_90'];
                $customer->aging_over_90    = $aging['over_90'];
                $customer->oldest_invoice_days = $oldestDays;

                return $customer;
            });

        $summary = [
            'current'      => $customers->sum('aging_current'),
            'days_31_60'   => $customers->sum('aging_31_60'),
            'days_61_90'   => $customers->sum('aging_61_90'),
            'days_over_90' => $customers->sum('aging_over_90'),
        ];
        $summary['total_outstanding'] = array_sum($summary);

        return [
            'customers'      => $customers,
            'summary'        => $summary,
            'customer_count' => $customers->count(),
        ];
    }

    /**
     * Get customer statement for a date range.
     */
    public function getCustomerStatement(Customer $customer, Carbon $from, Carbon $to): array
    {
        $openingBalanceCentavos = $this->creditTransactionRepo->getOpeningBalance($customer->id, $from);
        $transactions           = $this->creditTransactionRepo->getStatementTransactions($customer->id, $from, $to);

        $formattedTransactions = [];
        $runningBalance        = $openingBalanceCentavos;
        $totalCharges          = 0;
        $totalPayments         = 0;

        foreach ($transactions as $transaction) {
            $amountCentavos = $transaction->getRawOriginal('amount');
            $charge         = $transaction->type === 'charge'  ? abs($amountCentavos) / 100 : 0;
            $payment        = $transaction->type === 'payment' ? abs($amountCentavos) / 100 : 0;
            $runningBalance = $transaction->getRawOriginal('balance_after');

            $formattedTransactions[] = [
                'date'        => $transaction->transaction_date->toDateString(),
                'type'        => $transaction->type,
                'description' => $transaction->description,
                'reference'   => $transaction->reference_number,
                'sale_number' => $transaction->sale?->sale_number,
                'wallet_name' => $transaction->wallet?->name,          // MPC: show wallet name
                'due_date'    => $transaction->due_date?->toDateString(),
                'charges'     => $charge,
                'payments'    => $payment,
                'balance'     => $runningBalance / 100,
            ];

            $totalCharges  += $charge;
            $totalPayments += $payment;
        }

        return [
            'customer' => [
                'uuid'              => $customer->uuid,
                'code'              => $customer->code,
                'name'              => $customer->name,
                'address'           => $customer->address,
                'phone'             => $customer->phone,
                'email'             => $customer->email,
                'credit_limit'      => $customer->credit_limit,
                'credit_terms_days' => $customer->credit_terms_days,
                'is_member'         => $customer->is_member,    // MPC
                'member_id'         => $customer->member_id,    // MPC
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'opening_balance'  => $openingBalanceCentavos / 100,
            'transactions'     => $formattedTransactions,
            'closing_balance'  => $runningBalance / 100,
            'summary' => [
                'total_charges'  => $totalCharges,
                'total_payments' => $totalPayments,
                'net_change'     => $totalCharges - $totalPayments,
            ],
        ];
    }

    /**
     * Check if customer has available credit for a purchase (legacy flow).
     */
    public function checkCreditAvailability(Customer $customer, int $amount): array
    {
        $creditLimitCentavos  = (int) ($customer->getRawOriginal('credit_limit') ?? 0);
        $outstandingCentavos  = (int) ($customer->getRawOriginal('total_outstanding') ?? 0);
        $availableCentavos    = max(0, $creditLimitCentavos - $outstandingCentavos);

        return [
            'available'       => $amount <= $availableCentavos,
            'credit_limit'    => $creditLimitCentavos / 100,
            'outstanding'     => $outstandingCentavos / 100,
            'available_credit'=> $availableCentavos / 100,
            'requested'       => $amount / 100,
            'shortfall'       => max(0, ($amount - $availableCentavos) / 100),
        ];
    }

    /** Recalculate and persist customer total_outstanding from unpaid charges. */
    public function updateOutstandingBalance(Customer $customer): void
    {
        $totalOutstanding = $this->creditTransactionRepo->getTotalOutstanding($customer->id);

        $this->customerRepo->update($customer->id, [
            'total_outstanding' => max(0, $totalOutstanding),
        ]);
    }

    /** Adjust customer's credit limit (legacy single-limit). */
    public function adjustCreditLimit(Customer $customer, int $newLimit, string $reason): Customer
    {
        $oldLimit = $customer->getRawOriginal('credit_limit');

        DB::transaction(function () use ($customer, $newLimit, $oldLimit, $reason) {
            $this->customerRepo->update($customer->id, ['credit_limit' => $newLimit]);


        });

        return $this->customerRepo->find($customer->id);
    }

    /** Get overdue accounts. */
    public function getOverdueAccounts(Store $store): SupportCollection
    {
        return $this->customerRepo->getWithOverdueInvoices()
            ->map(function ($customer) {
                $overdueTransactions = $customer->creditTransactions;
                $totalOverdue        = $overdueTransactions->sum(fn ($t) => $t->getRawOriginal('amount')) / 100;
                $oldestTransaction   = $overdueTransactions->first();
                $daysOverdue         = $oldestTransaction
                    ? now()->diffInDays($oldestTransaction->due_date)
                    : 0;

                return [
                    'customer' => [
                        'uuid'      => $customer->uuid,
                        'code'      => $customer->code,
                        'name'      => $customer->name,
                        'phone'     => $customer->phone,
                        'email'     => $customer->email,
                        'is_member' => $customer->is_member,  // MPC
                        'member_id' => $customer->member_id,  // MPC
                    ],
                    'overdue_amount'   => $totalOverdue,
                    'days_overdue'     => $daysOverdue,
                    'oldest_due_date'  => $oldestTransaction?->due_date?->toDateString(),
                    'invoice_count'    => $overdueTransactions->count(),
                ];
            });
    }

    /**
     * Mark overdue transactions (scheduled command).
     *
     * @return int Number of unpaid charge records found.
     */
    public function markOverdueTransactions(): int
    {
        $count = $this->creditTransactionRepo->getUnpaidChargesCount();

        Log::info("Overdue check: {$count} unpaid charge records.");

        return $count;
    }
}
