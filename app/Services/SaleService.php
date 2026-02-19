<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\HeldTransaction;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CreditTransaction;
use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SaleService
{
    protected InventoryService $inventoryService;
    protected ProductRepositoryInterface $productRepo;
    protected SaleRepositoryInterface $saleRepo;
    protected CustomerRepositoryInterface $customerRepo;
    protected CreditTransactionRepositoryInterface $creditTransactionRepo;

    public function __construct(
        InventoryService $inventoryService,
        ProductRepositoryInterface $productRepo,
        SaleRepositoryInterface $saleRepo,
        CustomerRepositoryInterface $customerRepo,
        CreditTransactionRepositoryInterface $creditTransactionRepo
    ) {
        $this->inventoryService = $inventoryService;
        $this->productRepo = $productRepo;
        $this->saleRepo = $saleRepo;
        $this->customerRepo = $customerRepo;
        $this->creditTransactionRepo = $creditTransactionRepo;
    }

    /**
     * Create a new sale with 19-step process.
     *
     * @param array $data
     * @return Sale
     * @throws ValidationException
     */
    public function createSale(array $data): Sale
    {
        $user = Auth::user();
        $store = $user->store;

        // Step 1: Validate products belong to authenticated user's store
        $productIds = collect($data['items'])->pluck('product_id')->toArray();
        $this->validateProductsBelongToStore($productIds, $store->id);

        // Step 2: Load all products from database via repository
        $products = $this->productRepo->findManyByUuids($productIds)->keyBy('uuid');

        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages([
                'items' => ['One or more products not found or inactive.']
            ]);
        }

        // Step 3: Check stock availability
        $stockValidation = $this->inventoryService->validateStockAvailability($data['items']);
        if (!$stockValidation['available']) {
            throw ValidationException::withMessages([
                'items' => $stockValidation['errors']
            ]);
        }

        // Step 4-8: Calculate totals
        $calculations = $this->calculateTotals(
            $data['items'],
            $data['discount_type'] ?? null,
            $data['discount_value'] ?? null,
            $store->vat_rate ?? 12,
            $store->vat_inclusive ?? true
        );

        // Step 9: Validate payment amounts
        $paymentTotal = collect($data['payments'])->sum('amount');
        $difference = abs($calculations['total_amount'] - $paymentTotal);

        if ($difference > 1) { // Allow 1 centavo tolerance
            throw ValidationException::withMessages([
                'payments' => [
                    sprintf(
                        'Payment total (₱%s) does not match calculated total (₱%s). Difference: ₱%s',
                        number_format($paymentTotal / 100, 2),
                        number_format($calculations['total_amount'] / 100, 2),
                        number_format($difference / 100, 2)
                    )
                ]
            ]);
        }

        // Step 10: Validate credit payment
        $customer = null;
        $hasCreditPayment = collect($data['payments'])->contains('method', 'credit');

        if ($hasCreditPayment) {
            if (empty($data['customer_id'])) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Customer is required for credit payments.']
                ]);
            }

            $customer = $this->customerRepo->findByUuidOrFail($data['customer_id']);

            $this->validateCreditLimit($customer, $calculations['total_amount']);
        } elseif (!empty($data['customer_id'])) {
            $customer = $this->customerRepo->findByUuid($data['customer_id']);
        }

        // Step 11: Begin database transaction
        return DB::transaction(function () use ($data, $calculations, $products, $store, $user, $customer, $hasCreditPayment) {
            // Step 12: Generate sale number via repository
            $saleNumber = $this->saleRepo->getNextSaleNumber();

            // Step 13: Create Sale record via repository
            $sale = $this->saleRepo->create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'store_id' => $store->id,
                'branch_id' => $user->branch_id,
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'sale_number' => $saleNumber,
                'sale_date' => now(),
                'status' => 'completed',
                'price_tier' => $data['price_tier'],
                'subtotal' => $calculations['subtotal'],
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'discount_amount' => $calculations['discount_amount'],
                'vat_amount' => $calculations['vat_amount'],
                'total_amount' => $calculations['total_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Step 14: Create SaleItem records
            foreach ($data['items'] as $itemData) {
                $product = $products[$itemData['product_id']];

                // Calculate item discount
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                $itemDiscountAmount = 0;

                if (!empty($itemData['discount_type']) && !empty($itemData['discount_value'])) {
                    if ($itemData['discount_type'] === 'percentage') {
                        $itemDiscountAmount = $lineTotal * ($itemData['discount_value'] / 100);
                    } else {
                        $itemDiscountAmount = $itemData['discount_value'] * 100; // Convert to centavos
                    }
                }

                $lineTotalAfterDiscount = $lineTotal - $itemDiscountAmount;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount_type' => $itemData['discount_type'] ?? null,
                    'discount_value' => $itemData['discount_value'] ?? null,
                    'discount_amount' => round($itemDiscountAmount),
                    'line_total' => round($lineTotalAfterDiscount),
                ]);
            }

            // Step 15: Create SalePayment records
            foreach ($data['payments'] as $paymentData) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $paymentData['method'],
                    'amount' => $paymentData['amount'],
                    'reference_number' => $paymentData['reference_number'] ?? null,
                ]);
            }

            // Step 16: Deduct inventory
            foreach ($data['items'] as $itemData) {
                $product = $products[$itemData['product_id']];

                // Only deduct if product tracks inventory
                if ($product->track_inventory) {
                    $branch = $user->branch;
                    $this->inventoryService->deductStock(
                        $product,
                        $itemData['quantity'],
                        "Sale #{$saleNumber}",
                        $branch
                    );
                }
            }

            // Step 17: Handle credit payment
            if ($hasCreditPayment && $customer) {
                $creditAmount = collect($data['payments'])
                    ->where('method', 'credit')
                    ->sum('amount');

                // Update customer outstanding balance
                $customer->increment('total_outstanding', $creditAmount);

                // Create credit transaction record via repository
                $this->creditTransactionRepo->create([
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'type' => 'charge',
                    'amount' => $creditAmount,
                    'balance' => $customer->fresh()->total_outstanding,
                    'description' => "Credit charge for sale #{$saleNumber}",
                    'transaction_date' => now(),
                ]);
            }

            // Step 18: Update customer total purchases
            if ($customer) {
                $customer->increment('total_purchases', $calculations['total_amount']);
                $customer->update(['last_purchase_date' => now()]);
            }

            // Step 19: Load relationships and dispatch event
            $sale->load(['customer', 'items.product', 'payments', 'user', 'branch']);

            event(new SaleCompleted($sale));

            return $sale;
        });
    }

    /**
     * Void a sale with 9-step process.
     *
     * @param Sale $sale
     * @param string $reason
     * @return Sale
     * @throws ValidationException
     */
    public function voidSale(Sale $sale, string $reason): Sale
    {
        $user = Auth::user();

        // Step 1: Validate sale not already voided
        if ($sale->status === 'voided') {
            throw ValidationException::withMessages([
                'sale' => ['This sale has already been voided.']
            ]);
        }

        // Step 2: Validate sale is from same store
        if ($sale->store_id !== $user->store_id) {
            throw ValidationException::withMessages([
                'sale' => ['You do not have permission to void this sale.']
            ]);
        }

        // Step 3: Begin transaction
        return DB::transaction(function () use ($sale, $reason, $user) {
            // Step 4: Restore inventory
            foreach ($sale->items as $item) {
                $product = $item->product;

                // Only restore if product tracks inventory
                if ($product->track_inventory) {
                    $branch = $sale->branch;
                    $this->inventoryService->restoreStock(
                        $product,
                        $item->quantity,
                        "Void sale #{$sale->sale_number}",
                        $branch
                    );
                }
            }

            // Step 5: Reverse credit payment if exists
            $creditPayment = $sale->payments->firstWhere('method', 'credit');

            if ($creditPayment && $sale->customer) {
                $customer = $sale->customer;

                // Decrease outstanding balance
                $customer->decrement('total_outstanding', $creditPayment->amount);

                // Mark credit transaction as reversed - find via repository
                $creditTransactions = $this->creditTransactionRepo
                    ->where('sale_id', $sale->id)
                    ->where('type', 'charge')
                    ->all();

                $creditTransaction = $creditTransactions->first();

                if ($creditTransaction) {
                    $creditTransaction->update([
                        'is_reversed' => true,
                        'reversed_at' => now(),
                        'reversed_by' => $user->id,
                    ]);

                    // Create reversal transaction via repository
                    $this->creditTransactionRepo->create([
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'type' => 'reversal',
                        'amount' => -$creditPayment->amount,
                        'balance' => $customer->fresh()->total_outstanding,
                        'description' => "Reversal of sale #{$sale->sale_number} - {$reason}",
                        'transaction_date' => now(),
                    ]);
                }
            }

            // Step 6: Update sale status
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
            ]);

            // Step 7: Update customer total purchases
            if ($sale->customer) {
                $sale->customer->decrement('total_purchases', $sale->total_amount);
            }

            // Step 8: Reload relationships
            $sale->load(['customer', 'items.product', 'payments', 'user', 'branch', 'voidedBy']);

            // Dispatch event
            event(new SaleVoided($sale, $reason));

            // Step 9: Return updated sale
            return $sale;
        });
    }

    /**
     * Refund a sale (partial or full).
     *
     * @param Sale $sale
     * @param array|null $items
     * @param string $reason
     * @param string $refundMethod
     * @return Sale
     * @throws ValidationException
     */
    public function refundSale(Sale $sale, ?array $items, string $reason, string $refundMethod): Sale
    {
        $user = Auth::user();

        // Validate sale not voided
        if ($sale->status === 'voided') {
            throw ValidationException::withMessages([
                'sale' => ['Cannot refund a voided sale.']
            ]);
        }

        // Validate sale is from same store
        if ($sale->store_id !== $user->store_id) {
            throw ValidationException::withMessages([
                'sale' => ['You do not have permission to refund this sale.']
            ]);
        }

        return DB::transaction(function () use ($sale, $items, $reason, $refundMethod, $user) {
            $refundAmount = 0;

            // If no items specified, full refund
            if (empty($items)) {
                $items = $sale->items->map(function ($item) {
                    return [
                        'sale_item_id' => $item->id,
                        'quantity' => $item->quantity,
                    ];
                })->toArray();
            }

            // Process each refund item
            foreach ($items as $itemData) {
                $saleItem = SaleItem::findOrFail($itemData['sale_item_id']);

                // Calculate refund amount for this item
                $itemRefundAmount = ($saleItem->unit_price * $itemData['quantity']) -
                                   ($saleItem->discount_amount * ($itemData['quantity'] / $saleItem->quantity));

                $refundAmount += $itemRefundAmount;

                // Create negative sale item entry
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $saleItem->product_id,
                    'parent_sale_item_id' => $saleItem->id,
                    'quantity' => -$itemData['quantity'],
                    'unit_price' => $saleItem->unit_price,
                    'discount_type' => $saleItem->discount_type,
                    'discount_value' => $saleItem->discount_value,
                    'discount_amount' => -round($saleItem->discount_amount * ($itemData['quantity'] / $saleItem->quantity)),
                    'line_total' => -round($itemRefundAmount),
                ]);

                // Restore inventory
                $product = $saleItem->product;
                if ($product->track_inventory) {
                    $branch = $sale->branch;
                    $this->inventoryService->restoreStock(
                        $product,
                        $itemData['quantity'],
                        "Refund for sale #{$sale->sale_number} - {$reason}",
                        $branch
                    );
                }
            }

            // Create refund payment record (negative amount)
            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $refundMethod,
                'amount' => -round($refundAmount),
                'reference_number' => 'REFUND-' . now()->format('YmdHis'),
            ]);

            // Update sale status if full refund
            $totalRefunded = $sale->items()->where('quantity', '<', 0)->sum('line_total');
            $totalSold = $sale->items()->where('quantity', '>', 0)->sum('line_total');

            if (abs($totalRefunded) >= $totalSold) {
                $sale->update(['status' => 'refunded']);
            }

            // Update customer totals if applicable
            if ($sale->customer) {
                $sale->customer->decrement('total_purchases', round($refundAmount));
            }

            // Reload relationships
            $sale->load(['customer', 'items.product', 'payments', 'user', 'branch']);

            return $sale;
        });
    }

    /**
     * Hold a transaction for later.
     *
     * @param array $cartData
     * @param string $name
     * @return HeldTransaction
     */
    public function holdTransaction(array $cartData, string $name): HeldTransaction
    {
        $user = Auth::user();

        return HeldTransaction::create([
            'store_id' => $user->store_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'name' => $name,
            'cart_data' => $cartData,
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Resume a held transaction.
     *
     * @param HeldTransaction $held
     * @return array
     * @throws ValidationException
     */
    public function resumeTransaction(HeldTransaction $held): array
    {
        // Validate not expired
        if ($held->expires_at < now()) {
            throw ValidationException::withMessages([
                'transaction' => ['This held transaction has expired.']
            ]);
        }

        // Validate belongs to same store
        if ($held->store_id !== Auth::user()->store_id) {
            throw ValidationException::withMessages([
                'transaction' => ['You do not have permission to resume this transaction.']
            ]);
        }

        $cartData = $held->cart_data;

        // Delete the held transaction
        $held->delete();

        return $cartData;
    }

    /**
     * Discard a held transaction.
     *
     * @param HeldTransaction $held
     * @return bool
     * @throws ValidationException
     */
    public function discardHeldTransaction(HeldTransaction $held): bool
    {
        // Validate belongs to same store
        if ($held->store_id !== Auth::user()->store_id) {
            throw ValidationException::withMessages([
                'transaction' => ['You do not have permission to discard this transaction.']
            ]);
        }

        return $held->delete();
    }


    /**
     * Calculate all totals in centavos.
     *
     * @param array $items
     * @param string|null $discountType
     * @param float|null $discountValue
     * @param float $vatRate
     * @param bool $vatInclusive
     * @return array
     */
    public function calculateTotals(
        array $items,
        ?string $discountType,
        ?float $discountValue,
        float $vatRate,
        bool $vatInclusive
    ): array {
        // Calculate subtotal (sum of line totals after item discounts)
        $subtotal = 0;

        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];

            // Apply item-level discount
            if (!empty($item['discount_type']) && !empty($item['discount_value'])) {
                if ($item['discount_type'] === 'percentage') {
                    $lineTotal = $lineTotal - ($lineTotal * ($item['discount_value'] / 100));
                } else {
                    $lineTotal = $lineTotal - ($item['discount_value'] * 100); // Convert to centavos
                }
            }

            $subtotal += $lineTotal;
        }

        // Round subtotal
        $subtotal = round($subtotal);

        // Calculate order-level discount
        $discountAmount = 0;
        if (!empty($discountType) && !empty($discountValue)) {
            if ($discountType === 'percentage') {
                $discountAmount = $subtotal * ($discountValue / 100);
            } else {
                $discountAmount = $discountValue * 100; // Convert to centavos
            }
        }

        $discountAmount = round($discountAmount);
        $subtotalAfterDiscount = $subtotal - $discountAmount;

        // Calculate VAT
        $vatAmount = 0;
        $totalAmount = $subtotalAfterDiscount;

        if ($vatInclusive) {
            // VAT is already included in the price
            $vatAmount = $subtotalAfterDiscount * ($vatRate / (100 + $vatRate));
        } else {
            // VAT is added to the price
            $vatAmount = $subtotalAfterDiscount * ($vatRate / 100);
            $totalAmount = $subtotalAfterDiscount + $vatAmount;
        }

        $vatAmount = round($vatAmount);
        $totalAmount = round($totalAmount);

        return [
            'subtotal' => (int) $subtotal,
            'discount_amount' => (int) $discountAmount,
            'subtotal_after_discount' => (int) $subtotalAfterDiscount,
            'vat_amount' => (int) $vatAmount,
            'total_amount' => (int) $totalAmount,
        ];
    }

    /**
     * Validate products belong to store.
     *
     * @param array $productIds
     * @param int $storeId
     * @throws ValidationException
     */
    protected function validateProductsBelongToStore(array $productIds, int $storeId): void
    {
        // Use repository to find products - repository applies store_id filter automatically
        $count = $this->productRepo->findManyByUuids($productIds)->count();

        if ($count !== count($productIds)) {
            throw ValidationException::withMessages([
                'items' => ['One or more products do not belong to your store or are inactive.']
            ]);
        }
    }


    /**
     * Validate customer credit limit.
     *
     * @param Customer $customer
     * @param int $saleAmount
     * @throws ValidationException
     */
    protected function validateCreditLimit(Customer $customer, int $saleAmount): void
    {
        $availableCredit = $customer->credit_limit - $customer->total_outstanding;

        if ($availableCredit < $saleAmount) {
            throw ValidationException::withMessages([
                'payments' => [
                    sprintf(
                        'Customer credit limit exceeded. Available credit: ₱%s, Required: ₱%s',
                        number_format($availableCredit / 100, 2),
                        number_format($saleAmount / 100, 2)
                    )
                ]
            ]);
        }
    }
}
