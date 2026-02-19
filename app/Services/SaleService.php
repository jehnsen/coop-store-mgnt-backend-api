<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\WalletRestrictionException;
use App\Models\CustomerWallet;
use App\Models\HeldTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CustomerWalletRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    protected InventoryService                    $inventoryService;
    protected CreditService                       $creditService;       // MPC addition
    protected ProductRepositoryInterface          $productRepo;
    protected SaleRepositoryInterface             $saleRepo;
    protected CustomerRepositoryInterface         $customerRepo;
    protected CreditTransactionRepositoryInterface $creditTransactionRepo;
    protected CustomerWalletRepositoryInterface   $walletRepo;          // MPC addition

    public function __construct(
        InventoryService                    $inventoryService,
        CreditService                       $creditService,
        ProductRepositoryInterface          $productRepo,
        SaleRepositoryInterface             $saleRepo,
        CustomerRepositoryInterface         $customerRepo,
        CreditTransactionRepositoryInterface $creditTransactionRepo,
        CustomerWalletRepositoryInterface   $walletRepo,
    ) {
        $this->inventoryService      = $inventoryService;
        $this->creditService         = $creditService;
        $this->productRepo           = $productRepo;
        $this->saleRepo              = $saleRepo;
        $this->customerRepo          = $customerRepo;
        $this->creditTransactionRepo = $creditTransactionRepo;
        $this->walletRepo            = $walletRepo;
    }

    // =========================================================================
    // Sale Creation
    // =========================================================================

    /**
     * Create a new completed sale.
     *
     * MPC changes vs. original:
     *   - Customer is loaded early (before totals) so is_member can suppress VAT.
     *   - Wallet payments (method: 'wallet') are validated for category restrictions
     *     BEFORE the DB transaction opens.
     *   - Inside the DB transaction, each wallet payment calls
     *     CreditService::payWithWallet() which deducts the wallet balance and
     *     creates a linked CreditTransaction.
     *
     * @throws ValidationException
     * @throws WalletRestrictionException
     */
    public function createSale(array $data): Sale
    {
        $user  = Auth::user();
        $store = $user->store;

        // ------------------------------------------------------------------
        // Step 1: Validate products belong to this store
        // ------------------------------------------------------------------
        $productIds = collect($data['items'])->pluck('product_id')->toArray();
        $this->validateProductsBelongToStore($productIds, $store->id);

        // ------------------------------------------------------------------
        // Step 2: Load products (keyed by UUID for O(1) lookup)
        // ------------------------------------------------------------------
        $products = $this->productRepo->findManyByUuids($productIds)->keyBy('uuid');

        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages([
                'items' => ['One or more products not found or inactive.'],
            ]);
        }

        // ------------------------------------------------------------------
        // Step 2a (MPC): Early customer load for is_member flag & wallets
        // ------------------------------------------------------------------
        $customer          = null;
        $hasCreditPayment  = collect($data['payments'])->contains('method', 'credit');
        $walletPaymentRows = collect($data['payments'])->where('method', 'wallet');
        $hasWalletPayment  = $walletPaymentRows->isNotEmpty();

        if (!empty($data['customer_id'])) {
            $customer = $this->customerRepo->findByUuid($data['customer_id']);
        }

        // Customer is mandatory for credit or wallet payments
        if (($hasCreditPayment || $hasWalletPayment) && $customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => ['Customer is required for credit or wallet payments.'],
            ]);
        }

        // ------------------------------------------------------------------
        // Step 2b (MPC): Pre-load & validate each wallet BEFORE the transaction
        //
        //   - Each wallet payment row must carry a wallet_uuid.
        //   - The wallet must be active and belong to the customer.
        //   - validateWalletUsage() throws WalletRestrictionException if any
        //     cart item's category is not whitelisted in that wallet.
        // ------------------------------------------------------------------
        $walletsById = [];   // [wallet_uuid => CustomerWallet] map for reuse inside TX

        if ($hasWalletPayment) {
            foreach ($walletPaymentRows as $paymentRow) {
                $walletUuid = $paymentRow['wallet_uuid'];

                if (isset($walletsById[$walletUuid])) {
                    // Same wallet appears twice in the payments array; summing
                    // amounts is not supported – each wallet entry must be unique.
                    throw ValidationException::withMessages([
                        'payments' => ["Duplicate wallet UUID detected: {$walletUuid}. Each wallet may appear only once per sale."],
                    ]);
                }

                $wallet = $this->walletRepo->findByUuidAndCustomer(
                    $walletUuid,
                    $customer->id,
                );

                if ($wallet === null || $wallet->status !== 'active') {
                    throw ValidationException::withMessages([
                        'payments' => ["Wallet {$walletUuid} not found, inactive, or does not belong to this customer."],
                    ]);
                }

                // Category restriction guard — throws WalletRestrictionException
                $this->creditService->validateWalletUsage($wallet, $data['items'], $products);

                $walletsById[$walletUuid] = $wallet;
            }
        }

        // ------------------------------------------------------------------
        // Step 3: Check stock availability (service items skip this)
        // ------------------------------------------------------------------
        $stockValidation = $this->inventoryService->validateStockAvailability($data['items']);
        if (!$stockValidation['available']) {
            throw ValidationException::withMessages([
                'items' => $stockValidation['errors'],
            ]);
        }

        // ------------------------------------------------------------------
        // Steps 4-8 (MPC): Calculate totals
        //   If the customer is a cooperative member, VAT is waived entirely.
        //   Pass $isMember to calculateTotals() so it zeroes out the VAT.
        // ------------------------------------------------------------------
        $isMember = $customer?->is_member ?? false;

        $calculations = $this->calculateTotals(
            items:         $data['items'],
            discountType:  $data['discount_type']  ?? null,
            discountValue: $data['discount_value'] ?? null,
            vatRate:       $store->vat_rate        ?? 12,
            vatInclusive:  $store->vat_inclusive   ?? true,
            isMember:      $isMember,
        );

        // ------------------------------------------------------------------
        // Step 9: Validate payment total covers the calculated total
        // ------------------------------------------------------------------
        $paymentTotal = collect($data['payments'])->sum('amount');
        $difference   = abs($calculations['total_amount'] - $paymentTotal);

        if ($difference > 1) {   // 1-centavo rounding tolerance
            throw ValidationException::withMessages([
                'payments' => [sprintf(
                    'Payment total (₱%s) does not match calculated total (₱%s). Difference: ₱%s',
                    number_format($paymentTotal / 100, 2),
                    number_format($calculations['total_amount'] / 100, 2),
                    number_format($difference / 100, 2),
                )],
            ]);
        }

        // ------------------------------------------------------------------
        // Step 10: Validate legacy credit limit (only for method: 'credit')
        // ------------------------------------------------------------------
        if ($hasCreditPayment && $customer) {
            $this->validateCreditLimit($customer, $calculations['total_amount']);
        }

        // ------------------------------------------------------------------
        // Steps 11–19: Persist everything inside a single DB transaction
        // ------------------------------------------------------------------
        return DB::transaction(function () use (
            $data, $calculations, $products, $store, $user,
            $customer, $hasCreditPayment, $hasWalletPayment,
            $walletPaymentRows, $walletsById, $isMember
        ) {
            // Step 12: Generate unique, sequential sale number
            $saleNumber = $this->saleRepo->getNextSaleNumber();

            // Step 13: Create the Sale header
            $sale = $this->saleRepo->create([
                'uuid'           => \Illuminate\Support\Str::uuid(),
                'store_id'       => $store->id,
                'branch_id'      => $user->branch_id,
                'customer_id'    => $customer?->id,
                'user_id'        => $user->id,
                'sale_number'    => $saleNumber,
                'sale_date'      => now(),
                'status'         => 'completed',
                'price_tier'     => $data['price_tier'],
                'subtotal'       => $calculations['subtotal'],
                'discount_type'  => $data['discount_type']  ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'discount_amount'=> $calculations['discount_amount'],
                'vat_amount'     => $calculations['vat_amount'],
                'total_amount'   => $calculations['total_amount'],
                'notes'          => $data['notes'] ?? null,
            ]);

            // Step 14: Create SaleItem records
            foreach ($data['items'] as $itemData) {
                $product          = $products[$itemData['product_id']];
                $lineTotal        = $itemData['quantity'] * $itemData['unit_price'];
                $itemDiscountAmt  = 0;

                if (!empty($itemData['discount_type']) && !empty($itemData['discount_value'])) {
                    $itemDiscountAmt = $itemData['discount_type'] === 'percentage'
                        ? $lineTotal * ($itemData['discount_value'] / 100)
                        : $itemData['discount_value'] * 100;
                }

                SaleItem::create([
                    'sale_id'        => $sale->id,
                    'product_id'     => $product->id,
                    'quantity'       => $itemData['quantity'],
                    'unit_price'     => $itemData['unit_price'],
                    'discount_type'  => $itemData['discount_type']  ?? null,
                    'discount_value' => $itemData['discount_value'] ?? null,
                    'discount_amount'=> round($itemDiscountAmt),
                    'line_total'     => round($lineTotal - $itemDiscountAmt),
                ]);
            }

            // Step 15: Create SalePayment records + process wallet debits
            foreach ($data['payments'] as $paymentData) {
                SalePayment::create([
                    'sale_id'          => $sale->id,
                    'method'           => $paymentData['method'],
                    'amount'           => $paymentData['amount'],
                    'reference_number' => $paymentData['reference_number'] ?? null,
                ]);

                // MPC: debit the specific wallet and write a linked CreditTransaction
                if ($paymentData['method'] === 'wallet') {
                    $wallet = $walletsById[$paymentData['wallet_uuid']];
                    $this->creditService->payWithWallet($wallet, (int) $paymentData['amount'], $sale);
                }
            }

            // Step 16: Deduct inventory (skips is_service products)
            foreach ($data['items'] as $itemData) {
                $product = $products[$itemData['product_id']];

                if ($product->track_inventory) {
                    $this->inventoryService->deductStock(
                        $product,
                        $itemData['quantity'],
                        "Sale #{$saleNumber}",
                        $user->branch,
                    );
                }
            }

            // Step 17: Handle legacy credit payment
            if ($hasCreditPayment && $customer) {
                $creditAmount = collect($data['payments'])
                    ->where('method', 'credit')
                    ->sum('amount');

                $customer->increment('total_outstanding', $creditAmount);

                $this->creditTransactionRepo->create([
                    'customer_id'      => $customer->id,
                    'sale_id'          => $sale->id,
                    'type'             => 'charge',
                    'amount'           => $creditAmount,
                    'balance'          => $customer->fresh()->total_outstanding,
                    'description'      => "Credit charge for sale #{$saleNumber}",
                    'transaction_date' => now(),
                ]);
            }

            // Step 18: Update customer lifetime totals
            if ($customer) {
                $customer->increment('total_purchases', $calculations['total_amount']);

                // MPC: increment accumulated_patronage for cooperative dividend tracking
                if ($isMember) {
                    $customer->increment('accumulated_patronage', $calculations['total_amount']);
                }

                $customer->update(['last_purchase_date' => now()]);
            }

            // Step 19: Load relationships and dispatch event
            $sale->load(['customer', 'items.product', 'payments', 'user', 'branch']);
            event(new SaleCompleted($sale));

            return $sale;
        });
    }

    // =========================================================================
    // Void & Refund
    // =========================================================================

    /**
     * Void a sale, restoring inventory and reversing wallet/credit charges.
     *
     * MPC addition: if the original sale had wallet payments, each wallet's
     * balance is restored via CreditService::reverseWalletCharge().
     *
     * @throws ValidationException
     */
    public function voidSale(Sale $sale, string $reason): Sale
    {
        $user = Auth::user();

        if ($sale->status === 'voided') {
            throw ValidationException::withMessages([
                'sale' => ['This sale has already been voided.'],
            ]);
        }

        if ($sale->store_id !== $user->store_id) {
            throw ValidationException::withMessages([
                'sale' => ['You do not have permission to void this sale.'],
            ]);
        }

        return DB::transaction(function () use ($sale, $reason, $user) {
            // Restore inventory
            foreach ($sale->items as $item) {
                if ($item->product->track_inventory) {
                    $this->inventoryService->restoreStock(
                        $item->product,
                        $item->quantity,
                        "Void sale #{$sale->sale_number}",
                        $sale->branch,
                    );
                }
            }

            // Reverse payments
            foreach ($sale->payments as $payment) {
                if ($payment->method === 'wallet' && $sale->customer) {
                    // MPC: restore wallet balance and write reversal CreditTransaction
                    $walletTransaction = $this->creditTransactionRepo
                        ->where('sale_id', $sale->id)
                        ->where('type', 'charge')
                        ->all()
                        ->first(fn ($t) => $t->wallet_id !== null);

                    if ($walletTransaction?->wallet) {
                        $this->creditService->reverseWalletCharge(
                            wallet:         $walletTransaction->wallet,
                            amountCentavos: (int) abs($payment->getRawOriginal('amount') ?? $payment->amount * 100),
                            sale:           $sale,
                            reason:         $reason,
                        );
                    }
                }

                if ($payment->method === 'credit' && $sale->customer) {
                    $customer = $sale->customer;
                    $customer->decrement('total_outstanding', $payment->amount);

                    $creditTransaction = $this->creditTransactionRepo
                        ->where('sale_id', $sale->id)
                        ->where('type', 'charge')
                        ->all()
                        ->first(fn ($t) => $t->wallet_id === null);

                    if ($creditTransaction) {
                        $creditTransaction->update([
                            'is_reversed' => true,
                            'reversed_at' => now(),
                            'reversed_by' => $user->id,
                        ]);

                        $this->creditTransactionRepo->create([
                            'customer_id'      => $customer->id,
                            'sale_id'          => $sale->id,
                            'type'             => 'reversal',
                            'amount'           => -$payment->amount,
                            'balance'          => $customer->fresh()->total_outstanding,
                            'description'      => "Reversal of sale #{$sale->sale_number} – {$reason}",
                            'transaction_date' => now(),
                        ]);
                    }
                }
            }

            $sale->update([
                'status'     => 'voided',
                'voided_at'  => now(),
                'voided_by'  => $user->id,
                'void_reason'=> $reason,
            ]);

            if ($sale->customer) {
                $sale->customer->decrement('total_purchases', $sale->total_amount);
            }

            $sale->load(['customer', 'items.product', 'payments', 'user', 'branch', 'voidedBy']);
            event(new SaleVoided($sale, $reason));

            return $sale;
        });
    }

    /** Refund a sale (partial or full). */
    public function refundSale(Sale $sale, ?array $items, string $reason, string $refundMethod): Sale
    {
        $user = Auth::user();

        if ($sale->status === 'voided') {
            throw ValidationException::withMessages([
                'sale' => ['Cannot refund a voided sale.'],
            ]);
        }

        if ($sale->store_id !== $user->store_id) {
            throw ValidationException::withMessages([
                'sale' => ['You do not have permission to refund this sale.'],
            ]);
        }

        return DB::transaction(function () use ($sale, $items, $reason, $refundMethod, $user) {
            $refundAmount = 0;

            if (empty($items)) {
                $items = $sale->items->map(fn ($item) => [
                    'sale_item_id' => $item->id,
                    'quantity'     => $item->quantity,
                ])->toArray();
            }

            foreach ($items as $itemData) {
                $saleItem        = SaleItem::findOrFail($itemData['sale_item_id']);
                $itemRefundAmount = ($saleItem->unit_price * $itemData['quantity'])
                                  - ($saleItem->discount_amount * ($itemData['quantity'] / $saleItem->quantity));

                $refundAmount += $itemRefundAmount;

                SaleItem::create([
                    'sale_id'         => $sale->id,
                    'product_id'      => $saleItem->product_id,
                    'parent_sale_item_id' => $saleItem->id,
                    'quantity'        => -$itemData['quantity'],
                    'unit_price'      => $saleItem->unit_price,
                    'discount_type'   => $saleItem->discount_type,
                    'discount_value'  => $saleItem->discount_value,
                    'discount_amount' => -round($saleItem->discount_amount * ($itemData['quantity'] / $saleItem->quantity)),
                    'line_total'      => -round($itemRefundAmount),
                ]);

                if ($saleItem->product->track_inventory) {
                    $this->inventoryService->restoreStock(
                        $saleItem->product,
                        $itemData['quantity'],
                        "Refund for sale #{$sale->sale_number} – {$reason}",
                        $sale->branch,
                    );
                }
            }

            SalePayment::create([
                'sale_id'          => $sale->id,
                'method'           => $refundMethod,
                'amount'           => -round($refundAmount),
                'reference_number' => 'REFUND-' . now()->format('YmdHis'),
            ]);

            $totalRefunded = $sale->items()->where('quantity', '<', 0)->sum('line_total');
            $totalSold     = $sale->items()->where('quantity', '>', 0)->sum('line_total');

            if (abs($totalRefunded) >= $totalSold) {
                $sale->update(['status' => 'refunded']);
            }

            if ($sale->customer) {
                $sale->customer->decrement('total_purchases', round($refundAmount));
            }

            $sale->load(['customer', 'items.product', 'payments', 'user', 'branch']);

            return $sale;
        });
    }

    // =========================================================================
    // Held Transactions
    // =========================================================================

    public function holdTransaction(array $cartData, string $name): HeldTransaction
    {
        $user = Auth::user();

        return HeldTransaction::create([
            'store_id'  => $user->store_id,
            'branch_id' => $user->branch_id,
            'user_id'   => $user->id,
            'name'      => $name,
            'cart_data' => $cartData,
            'expires_at'=> now()->addHours(24),
        ]);
    }

    public function resumeTransaction(HeldTransaction $held): array
    {
        if ($held->expires_at < now()) {
            throw ValidationException::withMessages([
                'transaction' => ['This held transaction has expired.'],
            ]);
        }

        if ($held->store_id !== Auth::user()->store_id) {
            throw ValidationException::withMessages([
                'transaction' => ['You do not have permission to resume this transaction.'],
            ]);
        }

        $cartData = $held->cart_data;
        $held->delete();

        return $cartData;
    }

    public function discardHeldTransaction(HeldTransaction $held): bool
    {
        if ($held->store_id !== Auth::user()->store_id) {
            throw ValidationException::withMessages([
                'transaction' => ['You do not have permission to discard this transaction.'],
            ]);
        }

        return $held->delete();
    }

    // =========================================================================
    // Totals calculation
    // =========================================================================

    /**
     * Calculate all monetary totals, returning centavo integers.
     *
     * MPC change: $isMember = true zeroes out all VAT/tax regardless of the
     * store's vat_rate setting.  This reflects BIR-exempt cooperative sales
     * under RA 9520 (Philippine Cooperative Code).
     *
     * @param  array       $items
     * @param  string|null $discountType  'percentage' | 'fixed'
     * @param  float|null  $discountValue
     * @param  float       $vatRate       Percentage (e.g. 12 for 12 %)
     * @param  bool        $vatInclusive  True = VAT baked into prices
     * @param  bool        $isMember      MPC: suppress VAT for cooperative members
     * @return array{subtotal: int, discount_amount: int, subtotal_after_discount: int, vat_amount: int, total_amount: int}
     */
    public function calculateTotals(
        array   $items,
        ?string $discountType,
        ?float  $discountValue,
        float   $vatRate,
        bool    $vatInclusive,
        bool    $isMember = false,   // MPC
    ): array {
        // --- Item subtotal ---------------------------------------------------
        $subtotal = 0;

        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];

            if (!empty($item['discount_type']) && !empty($item['discount_value'])) {
                $lineTotal -= $item['discount_type'] === 'percentage'
                    ? $lineTotal * ($item['discount_value'] / 100)
                    : $item['discount_value'] * 100;
            }

            $subtotal += $lineTotal;
        }

        $subtotal = (int) round($subtotal);

        // --- Order-level discount --------------------------------------------
        $discountAmount = 0;

        if (!empty($discountType) && !empty($discountValue)) {
            $discountAmount = $discountType === 'percentage'
                ? $subtotal * ($discountValue / 100)
                : $discountValue * 100;
        }

        $discountAmount         = (int) round($discountAmount);
        $subtotalAfterDiscount  = $subtotal - $discountAmount;

        // --- VAT / Tax -------------------------------------------------------
        // MPC: cooperative members are VAT-exempt. Zero out tax entirely.
        $vatAmount   = 0;
        $totalAmount = $subtotalAfterDiscount;

        if (!$isMember && $vatRate > 0) {
            if ($vatInclusive) {
                // VAT already baked in: extract it from the price
                $vatAmount = $subtotalAfterDiscount * ($vatRate / (100 + $vatRate));
                // total_amount stays the same (VAT inclusive means no extra charge)
            } else {
                // VAT added on top
                $vatAmount   = $subtotalAfterDiscount * ($vatRate / 100);
                $totalAmount = $subtotalAfterDiscount + $vatAmount;
            }
        }

        return [
            'subtotal'               => $subtotal,
            'discount_amount'        => $discountAmount,
            'subtotal_after_discount'=> (int) $subtotalAfterDiscount,
            'vat_amount'             => (int) round($vatAmount),
            'total_amount'           => (int) round($totalAmount),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Confirm every UUID in $productIds resolves to a product in $storeId.
     * The repository already applies the store_id global scope, so a count
     * mismatch means a product belongs to another store or doesn't exist.
     *
     * @throws ValidationException
     */
    protected function validateProductsBelongToStore(array $productIds, int $storeId): void
    {
        $count = $this->productRepo->findManyByUuids($productIds)->count();

        if ($count !== count($productIds)) {
            throw ValidationException::withMessages([
                'items' => ['One or more products do not belong to your store or are inactive.'],
            ]);
        }
    }

    /**
     * Validate legacy single credit_limit on customer (non-wallet credit flow).
     *
     * @throws ValidationException
     */
    protected function validateCreditLimit(\App\Models\Customer $customer, int $saleAmount): void
    {
        $creditLimitCentavos  = (int) ($customer->getRawOriginal('credit_limit') ?? 0);
        $outstandingCentavos  = (int) ($customer->getRawOriginal('total_outstanding') ?? 0);
        $availableCentavos    = $creditLimitCentavos - $outstandingCentavos;

        if ($availableCentavos < $saleAmount) {
            throw ValidationException::withMessages([
                'payments' => [sprintf(
                    'Customer credit limit exceeded. Available credit: ₱%s, Required: ₱%s',
                    number_format($availableCentavos / 100, 2),
                    number_format($saleAmount / 100, 2),
                )],
            ]);
        }
    }
}
