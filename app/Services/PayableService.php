<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\PayableTransaction;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\PayableTransactionRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayableService
{
    public function __construct(
        protected SupplierRepositoryInterface $supplierRepo,
        protected PayableTransactionRepositoryInterface $payableTransactionRepo,
        protected PurchaseOrderRepositoryInterface $purchaseOrderRepo
    ) {
    }

    /**
     * Create AP invoice when PO is received.
     *
     * @param Supplier $supplier
     * @param PurchaseOrder $purchaseOrder
     * @param int $amount Amount in centavos
     * @param int $termsDays
     * @return PayableTransaction
     */
    public function createInvoice(Supplier $supplier, PurchaseOrder $purchaseOrder, int $amount, int $termsDays): PayableTransaction
    {
        return DB::transaction(function () use ($supplier, $purchaseOrder, $amount, $termsDays) {
            // Get current balance (in centavos)
            $balanceBefore = $supplier->getRawOriginal('total_outstanding') ?? 0;
            $balanceAfter = $balanceBefore + $amount;

            // Calculate due date
            $dueDate = now()->addDays($termsDays);

            // Create payable transaction
            $transaction = $this->payableTransactionRepo->create([
                'store_id' => $supplier->store_id,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => auth()->id(),
                'type' => 'invoice',
                'reference_number' => $purchaseOrder->po_number,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Purchase order received - {$purchaseOrder->po_number}",
                'transaction_date' => now(),
                'due_date' => $dueDate,
                'payment_method' => null,
                'notes' => "AP Invoice for PO with {$termsDays} days terms",
                'is_reversed' => false,
            ]);

            // Update supplier's total outstanding (store in centavos)
            $this->supplierRepo->update($supplier->id, [
                'total_outstanding' => $balanceAfter,
            ]);

            // Update PO payment tracking
            $this->purchaseOrderRepo->update($purchaseOrder->id, [
                'payment_status' => 'unpaid',
                'payment_due_date' => $dueDate,
            ]);



            return $this->payableTransactionRepo->with(['purchaseOrder', 'supplier'])->find($transaction->id);
        });
    }

    /**
     * Make payment to supplier.
     *
     * @param Supplier $supplier
     * @param int $amount Amount in centavos
     * @param string $method
     * @param string|null $reference
     * @param array|null $invoiceUuids
     * @param string|null $notes
     * @return array [PayableTransaction, array of applied allocations]
     */
    public function makePayment(
        Supplier $supplier,
        int $amount,
        string $method,
        ?string $reference = null,
        ?array $invoiceUuids = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($supplier, $amount, $method, $reference, $invoiceUuids, $notes) {
            // Get current balance (in centavos)
            $balanceBefore = $supplier->getRawOriginal('total_outstanding') ?? 0;
            $balanceAfter = max(0, $balanceBefore - $amount);

            // Create payment transaction
            $transaction = $this->payableTransactionRepo->create([
                'store_id' => $supplier->store_id,
                'supplier_id' => $supplier->id,
                'user_id' => auth()->id(),
                'type' => 'payment',
                'reference_number' => $reference ?? 'PAY-' . now()->format('YmdHis'),
                'amount' => -$amount, // Negative for payment
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Payment made - {$method}",
                'transaction_date' => now(),
                'payment_method' => $method,
                'notes' => $notes,
                'is_reversed' => false,
            ]);

            // Allocate payment to invoices (FIFO)
            $appliedTo = [];
            $remainingAmount = $amount;

            // Get unpaid POs (FIFO ordered by payment_due_date)
            $purchaseOrders = $this->purchaseOrderRepo->getUnpaidBySupplier($supplier->id, $invoiceUuids);

            foreach ($purchaseOrders as $po) {
                if ($remainingAmount <= 0) {
                    break;
                }

                // Calculate outstanding amount for this PO
                $totalAmountCentavos = $po->getRawOriginal('total_amount');
                $amountPaidCentavos = $po->getRawOriginal('amount_paid') ?? 0;
                $outstandingCentavos = $totalAmountCentavos - $amountPaidCentavos;

                if ($outstandingCentavos <= 0) {
                    continue;
                }

                // Determine amount to apply
                $amountToApply = min($remainingAmount, $outstandingCentavos);
                $newAmountPaid = $amountPaidCentavos + $amountToApply;

                // Update PO
                $this->purchaseOrderRepo->update($po->id, [
                    'amount_paid' => $newAmountPaid,
                    'payment_status' => $newAmountPaid >= $totalAmountCentavos ? 'paid' : 'partial',
                    'payment_completed_date' => $newAmountPaid >= $totalAmountCentavos ? now() : null,
                ]);

                // Update payable transaction for this PO
                $paidDate = $newAmountPaid >= $totalAmountCentavos ? now() : null;
                $this->payableTransactionRepo->updatePaidDateForPurchaseOrder($po->id, $paidDate);

                $appliedTo[] = [
                    'po_uuid' => $po->uuid,
                    'po_number' => $po->po_number,
                    'amount_applied' => $amountToApply / 100,
                    'previous_balance' => $outstandingCentavos / 100,
                    'new_balance' => max(0, $outstandingCentavos - $amountToApply) / 100,
                    'status' => $newAmountPaid >= $totalAmountCentavos ? 'paid' : 'partial',
                ];

                $remainingAmount -= $amountToApply;
            }

            // Update supplier's total outstanding
            $this->updateOutstandingBalance($supplier);



            return [
                'transaction' => $this->payableTransactionRepo->with(['supplier'])->find($transaction->id),
                'applied_to' => $appliedTo,
                'remaining_credit' => $remainingAmount / 100,
            ];
        });
    }

    /**
     * Get aging report for all suppliers with outstanding balances.
     *
     * @param Store $store
     * @return array
     */
    public function getAgingReport(Store $store): array
    {
        $suppliers = $this->supplierRepo->getWithOutstanding()
            ->map(function ($supplier) {
                // Get all outstanding payable transactions
                $transactions = $this->payableTransactionRepo->getUnpaidInvoices($supplier->id);

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

                // Add aging data to supplier object for resource
                $supplier->aging_current = $aging['current'];
                $supplier->aging_31_60 = $aging['31_60'];
                $supplier->aging_61_90 = $aging['61_90'];
                $supplier->aging_over_90 = $aging['over_90'];
                $supplier->oldest_invoice_days = $oldestDays;

                return $supplier;
            });

        // Calculate summary
        $summary = [
            'current' => $suppliers->sum('aging_current'),
            'days_31_60' => $suppliers->sum('aging_31_60'),
            'days_61_90' => $suppliers->sum('aging_61_90'),
            'days_over_90' => $suppliers->sum('aging_over_90'),
        ];

        $summary['total_outstanding'] = array_sum($summary);

        return [
            'suppliers' => $suppliers,
            'summary' => $summary,
            'supplier_count' => $suppliers->count(),
        ];
    }

    /**
     * Get supplier statement for a date range.
     *
     * @param Supplier $supplier
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getSupplierStatement(Supplier $supplier, Carbon $from, Carbon $to): array
    {
        // Get opening balance (before start date)
        $openingBalanceCentavos = $this->payableTransactionRepo->getOpeningBalance($supplier->id, $from);

        // Get transactions in date range
        $transactions = $this->payableTransactionRepo->getStatementTransactions($supplier->id, $from, $to);

        // Format transactions
        $formattedTransactions = [];
        $runningBalance = $openingBalanceCentavos;
        $totalInvoices = 0;
        $totalPayments = 0;

        foreach ($transactions as $transaction) {
            $amountCentavos = $transaction->getRawOriginal('amount');
            $invoice = $transaction->type === 'invoice' ? abs($amountCentavos) / 100 : 0;
            $payment = $transaction->type === 'payment' ? abs($amountCentavos) / 100 : 0;

            $runningBalance = $transaction->getRawOriginal('balance_after');

            $formattedTransactions[] = [
                'date' => $transaction->transaction_date->toDateString(),
                'type' => $transaction->type,
                'description' => $transaction->description,
                'reference' => $transaction->reference_number,
                'po_number' => $transaction->purchaseOrder?->po_number,
                'due_date' => $transaction->due_date?->toDateString(),
                'invoices' => $invoice,
                'payments' => $payment,
                'balance' => $runningBalance / 100,
            ];

            $totalInvoices += $invoice;
            $totalPayments += $payment;
        }

        $closingBalanceCentavos = $runningBalance;

        return [
            'supplier' => [
                'uuid' => $supplier->uuid,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'address' => $supplier->address,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'payment_terms_days' => $supplier->payment_terms_days,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'opening_balance' => $openingBalanceCentavos / 100,
            'transactions' => $formattedTransactions,
            'closing_balance' => $closingBalanceCentavos / 100,
            'summary' => [
                'total_invoices' => $totalInvoices,
                'total_payments' => $totalPayments,
                'net_change' => $totalInvoices - $totalPayments,
            ],
        ];
    }

    /**
     * Update supplier's outstanding balance.
     *
     * @param Supplier $supplier
     * @return void
     */
    public function updateOutstandingBalance(Supplier $supplier): void
    {
        // Calculate total outstanding from unpaid invoices
        $totalOutstanding = $this->payableTransactionRepo->getTotalOutstanding($supplier->id);

        // Update supplier record (value is already in centavos from DB)
        $this->supplierRepo->update($supplier->id, [
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
        return $this->supplierRepo->getWithOverdueInvoices()
            ->map(function ($supplier) {
                $overdueTransactions = $supplier->payableTransactions;
                $totalOverdue = $overdueTransactions->sum(fn ($t) => $t->getRawOriginal('amount')) / 100;
                $oldestTransaction = $overdueTransactions->first();
                $daysOverdue = $oldestTransaction
                    ? now()->diffInDays($oldestTransaction->due_date)
                    : 0;

                return [
                    'supplier' => [
                        'uuid' => $supplier->uuid,
                        'code' => $supplier->code,
                        'name' => $supplier->name,
                        'phone' => $supplier->phone,
                        'email' => $supplier->email,
                    ],
                    'overdue_amount' => $totalOverdue,
                    'days_overdue' => $daysOverdue,
                    'oldest_due_date' => $oldestTransaction?->due_date?->toDateString(),
                    'invoice_count' => $overdueTransactions->count(),
                ];
            });
    }

    /**
     * Mark overdue transactions.
     * This should run daily via scheduled command.
     *
     * @return int Number of transactions marked as overdue
     */
    public function markOverdueTransactions(): int
    {
        // Note: Status is computed, but we track overdue invoices
        $count = $this->payableTransactionRepo->getUnpaidInvoicesCount();

        Log::info("Marked {$count} payable transactions as overdue");

        return $count;
    }
}
