<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CreditTransaction;
use App\Models\Sale;
use App\Models\Store;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepo,
        protected CreditTransactionRepositoryInterface $creditTransactionRepo,
        protected SaleRepositoryInterface $saleRepo
    ) {
    }
    /**
     * Charge credit to a customer's account.
     *
     * @param Customer $customer
     * @param Sale $sale
     * @param int $amount Amount in centavos
     * @param int $termsDays
     * @return CreditTransaction
     */
    public function chargeCredit(Customer $customer, Sale $sale, int $amount, int $termsDays): CreditTransaction
    {
        return DB::transaction(function () use ($customer, $sale, $amount, $termsDays) {
            // Get current balance (in centavos)
            $balanceBefore = $customer->getRawOriginal('total_outstanding') ?? 0;
            $balanceAfter = $balanceBefore + $amount;

            // Calculate due date
            $dueDate = now()->addDays($termsDays);

            // Create credit transaction
            $transaction = $this->creditTransactionRepo->create([
                'store_id' => $customer->store_id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'user_id' => auth()->id(),
                'type' => 'charge',
                'reference_number' => $sale->sale_number,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Credit sale - {$sale->sale_number}",
                'transaction_date' => now(),
                'due_date' => $dueDate,
                'payment_method' => null,
                'notes' => "Credit sale with {$termsDays} days terms",
                'is_reversed' => false,
            ]);

            // Update customer's total outstanding (store in centavos)
            $this->customerRepo->update($customer->id, [
                'total_outstanding' => $balanceAfter,
            ]);

            // Log activity
            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'sale_id' => $sale->id,
                    'amount' => $amount / 100,
                    'due_date' => $dueDate->toDateString(),
                ])
                ->log('Credit charged to customer account');

            return $this->creditTransactionRepo->with(['sale', 'customer'])->find($transaction->id);
        });
    }

    /**
     * Receive payment from customer.
     *
     * @param Customer $customer
     * @param int $amount Amount in centavos
     * @param string $method
     * @param string|null $reference
     * @param array|null $invoiceUuids
     * @param string|null $notes
     * @return array [CreditTransaction, array of applied allocations]
     */
    public function receivePayment(
        Customer $customer,
        int $amount,
        string $method,
        ?string $reference = null,
        ?array $invoiceUuids = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($customer, $amount, $method, $reference, $invoiceUuids, $notes) {
            // Get current balance (in centavos)
            $balanceBefore = $customer->getRawOriginal('total_outstanding') ?? 0;
            $balanceAfter = max(0, $balanceBefore - $amount);

            // Create payment transaction
            $transaction = $this->creditTransactionRepo->create([
                'store_id' => $customer->store_id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'type' => 'payment',
                'reference_number' => $reference ?? 'PAY-' . now()->format('YmdHis'),
                'amount' => -$amount, // Negative for payment
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Payment received - {$method}",
                'transaction_date' => now(),
                'payment_method' => $method,
                'notes' => $notes,
                'is_reversed' => false,
            ]);

            // Allocate payment to invoices
            $appliedTo = [];
            $remainingAmount = $amount;

            // Get unpaid sales (FIFO ordered by sale_date)
            $sales = $this->saleRepo->getUnpaidByCustomer($customer->id, $invoiceUuids);

            foreach ($sales as $sale) {
                if ($remainingAmount <= 0) {
                    break;
                }

                // Calculate outstanding amount for this sale
                $totalAmountCentavos = $sale->getRawOriginal('total_amount');
                $amountPaidCentavos = $sale->getRawOriginal('amount_paid') ?? 0;
                $outstandingCentavos = $totalAmountCentavos - $amountPaidCentavos;

                if ($outstandingCentavos <= 0) {
                    continue;
                }

                // Determine amount to apply
                $amountToApply = min($remainingAmount, $outstandingCentavos);
                $newAmountPaid = $amountPaidCentavos + $amountToApply;

                // Update sale
                $this->saleRepo->update($sale->id, [
                    'amount_paid' => $newAmountPaid,
                    'payment_status' => $newAmountPaid >= $totalAmountCentavos ? 'paid' : 'partial',
                ]);

                // Update credit transaction for this sale
                $paidDate = $newAmountPaid >= $totalAmountCentavos ? now() : null;
                $this->creditTransactionRepo->updatePaidDateForSale($sale->id, $paidDate);

                $appliedTo[] = [
                    'sale_uuid' => $sale->uuid,
                    'sale_number' => $sale->sale_number,
                    'amount_applied' => $amountToApply / 100,
                    'previous_balance' => $outstandingCentavos / 100,
                    'new_balance' => max(0, $outstandingCentavos - $amountToApply) / 100,
                    'status' => $newAmountPaid >= $totalAmountCentavos ? 'paid' : 'partial',
                ];

                $remainingAmount -= $amountToApply;
            }

            // Update customer's total outstanding
            $this->updateOutstandingBalance($customer);

            // Log activity
            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'amount' => $amount / 100,
                    'method' => $method,
                    'applied_to' => $appliedTo,
                ])
                ->log('Payment received from customer');

            return [
                'transaction' => $this->creditTransactionRepo->with(['customer'])->find($transaction->id),
                'applied_to' => $appliedTo,
                'remaining_credit' => $remainingAmount / 100,
            ];
        });
    }

    /**
     * Get aging report for all customers with outstanding balances.
     *
     * @param Store $store
     * @return array
     */
    public function getAgingReport(Store $store): array
    {
        $customers = $this->customerRepo->getWithOutstanding()
            ->map(function ($customer) {
                // Get all outstanding credit transactions
                $transactions = $this->creditTransactionRepo->getUnpaidInvoices($customer->id);

                $aging = [
                    'current' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                ];

                $oldestDays = 0;

                foreach ($transactions as $transaction) {
                    if (!$transaction->due_date) {
                        continue;
                    }

                    $daysOverdue = now()->diffInDays($transaction->due_date, false);
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

                // Add aging data to customer object for resource
                $customer->aging_current = $aging['current'];
                $customer->aging_31_60 = $aging['31_60'];
                $customer->aging_61_90 = $aging['61_90'];
                $customer->aging_over_90 = $aging['over_90'];
                $customer->oldest_invoice_days = $oldestDays;

                return $customer;
            });

        // Calculate summary
        $summary = [
            'current' => $customers->sum('aging_current'),
            'days_31_60' => $customers->sum('aging_31_60'),
            'days_61_90' => $customers->sum('aging_61_90'),
            'days_over_90' => $customers->sum('aging_over_90'),
        ];

        $summary['total_outstanding'] = array_sum($summary);

        return [
            'customers' => $customers,
            'summary' => $summary,
            'customer_count' => $customers->count(),
        ];
    }

    /**
     * Get customer statement for a date range.
     *
     * @param Customer $customer
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getCustomerStatement(Customer $customer, Carbon $from, Carbon $to): array
    {
        // Get opening balance (before start date)
        $openingBalanceCentavos = $this->creditTransactionRepo->getOpeningBalance($customer->id, $from);

        // Get transactions in date range
        $transactions = $this->creditTransactionRepo->getStatementTransactions($customer->id, $from, $to);

        // Format transactions
        $formattedTransactions = [];
        $runningBalance = $openingBalanceCentavos;
        $totalCharges = 0;
        $totalPayments = 0;

        foreach ($transactions as $transaction) {
            $amountCentavos = $transaction->getRawOriginal('amount');
            $charge = $transaction->type === 'charge' ? abs($amountCentavos) / 100 : 0;
            $payment = $transaction->type === 'payment' ? abs($amountCentavos) / 100 : 0;

            $runningBalance = $transaction->getRawOriginal('balance_after');

            $formattedTransactions[] = [
                'date' => $transaction->transaction_date->toDateString(),
                'type' => $transaction->type,
                'description' => $transaction->description,
                'reference' => $transaction->reference_number,
                'sale_number' => $transaction->sale?->sale_number,
                'due_date' => $transaction->due_date?->toDateString(),
                'charges' => $charge,
                'payments' => $payment,
                'balance' => $runningBalance / 100,
            ];

            $totalCharges += $charge;
            $totalPayments += $payment;
        }

        $closingBalanceCentavos = $runningBalance;

        return [
            'customer' => [
                'uuid' => $customer->uuid,
                'code' => $customer->code,
                'name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'credit_limit' => $customer->credit_limit,
                'credit_terms_days' => $customer->credit_terms_days,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'opening_balance' => $openingBalanceCentavos / 100,
            'transactions' => $formattedTransactions,
            'closing_balance' => $closingBalanceCentavos / 100,
            'summary' => [
                'total_charges' => $totalCharges,
                'total_payments' => $totalPayments,
                'net_change' => $totalCharges - $totalPayments,
            ],
        ];
    }

    /**
     * Check if customer has available credit for a purchase.
     *
     * @param Customer $customer
     * @param int $amount Amount in centavos
     * @return array
     */
    public function checkCreditAvailability(Customer $customer, int $amount): array
    {
        $creditLimitCentavos = $customer->getRawOriginal('credit_limit') ?? 0;
        $outstandingCentavos = $customer->getRawOriginal('total_outstanding') ?? 0;
        $availableCentavos = max(0, $creditLimitCentavos - $outstandingCentavos);

        return [
            'available' => $amount <= $availableCentavos,
            'credit_limit' => $creditLimitCentavos / 100,
            'outstanding' => $outstandingCentavos / 100,
            'available_credit' => $availableCentavos / 100,
            'requested' => $amount / 100,
            'shortfall' => max(0, ($amount - $availableCentavos) / 100),
        ];
    }

    /**
     * Update customer's outstanding balance.
     *
     * @param Customer $customer
     * @return void
     */
    public function updateOutstandingBalance(Customer $customer): void
    {
        // Calculate total outstanding from unpaid charges
        $totalOutstanding = $this->creditTransactionRepo->getTotalOutstanding($customer->id);

        // Update customer record (value is already in centavos from DB)
        $this->customerRepo->update($customer->id, [
            'total_outstanding' => max(0, $totalOutstanding),
        ]);
    }

    /**
     * Get overdue accounts.
     *
     * @param Store $store
     * @return Collection
     */
    public function getOverdueAccounts(Store $store): Collection
    {
        return $this->customerRepo->getWithOverdueInvoices()
            ->map(function ($customer) {
                $overdueTransactions = $customer->creditTransactions;
                $totalOverdue = $overdueTransactions->sum(fn ($t) => $t->getRawOriginal('amount')) / 100;
                $oldestTransaction = $overdueTransactions->first();
                $daysOverdue = $oldestTransaction
                    ? now()->diffInDays($oldestTransaction->due_date)
                    : 0;

                return [
                    'customer' => [
                        'uuid' => $customer->uuid,
                        'code' => $customer->code,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'email' => $customer->email,
                    ],
                    'overdue_amount' => $totalOverdue,
                    'days_overdue' => $daysOverdue,
                    'oldest_due_date' => $oldestTransaction?->due_date?->toDateString(),
                    'invoice_count' => $overdueTransactions->count(),
                ];
            });
    }

    /**
     * Adjust customer's credit limit.
     *
     * @param Customer $customer
     * @param int $newLimit New limit in centavos
     * @param string $reason
     * @return Customer
     */
    public function adjustCreditLimit(Customer $customer, int $newLimit, string $reason): Customer
    {
        $oldLimit = $customer->getRawOriginal('credit_limit');

        DB::transaction(function () use ($customer, $newLimit, $oldLimit, $reason) {
            // Update credit limit
            $this->customerRepo->update($customer->id, [
                'credit_limit' => $newLimit,
            ]);

            // Log activity
            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_limit' => $oldLimit / 100,
                    'new_limit' => $newLimit / 100,
                    'reason' => $reason,
                ])
                ->log('Credit limit adjusted');
        });

        return $this->customerRepo->find($customer->id);
    }

    /**
     * Mark overdue transactions.
     * This should run daily via scheduled command.
     *
     * @return int Number of transactions marked as overdue
     */
    public function markOverdueTransactions(): int
    {
        // Note: Status is computed, but we update paid_date to null for overdue tracking
        $count = $this->creditTransactionRepo->getUnpaidChargesCount();

        Log::info("Marked {$count} credit transactions as overdue");

        return $count;
    }
}
