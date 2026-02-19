# Sales (Point of Sale) API - Complete Implementation

## Overview

This is the **MOST CRITICAL** component of the HardwarePOS system. The Sales API handles all point-of-sale transactions with a robust 19-step transaction engine that ensures data integrity, accurate inventory tracking, and proper financial calculations.

## Core Features

- **Complete Transaction Processing**: Create, void, and refund sales
- **Multi-Payment Support**: Accept multiple payment methods per sale
- **VAT Calculations**: Support both VAT-inclusive and VAT-exclusive pricing
- **Credit Management**: Track customer credit limits and outstanding balances
- **Inventory Integration**: Automatic stock deduction and restoration
- **Held Transactions**: Park incomplete transactions for later completion
- **Receipt Generation**: Thermal printer-ready receipts and PDF exports
- **Audit Trail**: Complete activity logging for all operations

## File Structure

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   └── SaleController.php              # Main API controller
│   ├── Requests/Sale/
│   │   ├── StoreSaleRequest.php            # Sale creation validation
│   │   ├── VoidSaleRequest.php             # Void sale validation
│   │   ├── RefundSaleRequest.php           # Refund validation
│   │   └── HoldTransactionRequest.php      # Hold transaction validation
│   └── Resources/
│       ├── SaleResource.php                # Sale data transformation
│       ├── SaleItemResource.php            # Sale item transformation
│       ├── SalePaymentResource.php         # Payment transformation
│       └── ReceiptResource.php             # Receipt formatting
├── Services/
│   └── SaleService.php                     # Core business logic
├── Events/
│   ├── SaleCompleted.php                   # Sale creation event
│   └── SaleVoided.php                      # Sale void event
└── Listeners/
    ├── UpdateCustomerTotals.php            # Customer metrics update
    └── LogSaleActivity.php                 # Activity logging

resources/views/receipts/
└── sale.blade.php                          # PDF receipt template
```

## API Endpoints

### Sales Management

#### 1. Get Sales List (Paginated)
```http
GET /api/v1/sales
```

**Query Parameters:**
- `status`: Filter by status (completed, voided, refunded)
- `customer_id`: Filter by customer UUID
- `date_from`: Start date (YYYY-MM-DD)
- `date_to`: End date (YYYY-MM-DD)
- `cashier_id`: Filter by cashier UUID
- `search`: Search by sale number
- `per_page`: Items per page (default: 15, max: 100)

**Response:**
```json
{
  "success": true,
  "message": "Sales retrieved successfully.",
  "data": {
    "data": [...],
    "current_page": 1,
    "total": 100,
    "per_page": 15
  }
}
```

#### 2. Create Sale
```http
POST /api/v1/sales
```

**Request Body:**
```json
{
  "customer_id": "uuid-or-null",
  "price_tier": "retail",
  "items": [
    {
      "product_id": "product-uuid",
      "quantity": 10,
      "unit_price": 28000,
      "discount_type": "percentage",
      "discount_value": 5
    }
  ],
  "discount_type": "fixed",
  "discount_value": 5000,
  "payments": [
    {
      "method": "cash",
      "amount": 250000,
      "reference_number": null
    }
  ],
  "notes": "Customer requested delivery"
}
```

**Important Notes:**
- All `unit_price` and `amount` values are in **CENTAVOS** (integer)
- `price_tier`: retail, wholesale, or contractor
- `discount_type`: percentage or fixed
- `payment.method`: cash, gcash, maya, bank_transfer, check, or credit
- If payment method is `credit`, `customer_id` is required
- Payment total must match calculated sale total (±1 centavo tolerance)

**Response:**
```json
{
  "success": true,
  "message": "Sale created successfully.",
  "data": {
    "uuid": "...",
    "sale_number": "INV-2026-000001",
    "total_amount": "2500.00",
    ...
  }
}
```

#### 3. Get Single Sale
```http
GET /api/v1/sales/{uuid}
```

**Response:**
Returns complete sale details with all items, payments, customer, and cashier info.

#### 4. Void Sale
```http
POST /api/v1/sales/{uuid}/void
```

**Request Body:**
```json
{
  "reason": "Customer cancelled order"
}
```

**What Happens:**
1. Sale status changed to 'voided'
2. Inventory restored for all items
3. Credit transactions reversed (if applicable)
4. Customer totals updated
5. Activity logged

**Response:**
Returns updated sale with void information.

#### 5. Refund Sale
```http
POST /api/v1/sales/{uuid}/refund
```

**Request Body (Partial Refund):**
```json
{
  "items": [
    {
      "sale_item_id": 123,
      "quantity": 5
    }
  ],
  "reason": "Defective items",
  "refund_method": "cash"
}
```

**Request Body (Full Refund):**
```json
{
  "items": null,
  "reason": "Customer returned all items",
  "refund_method": "cash"
}
```

**What Happens:**
1. Negative sale items created for refunded quantities
2. Inventory restored
3. Refund payment record created
4. Customer totals adjusted
5. Sale status updated to 'refunded' if fully refunded

### Receipt Operations

#### 6. Get Receipt (JSON)
```http
GET /api/v1/sales/{uuid}/receipt
```

Returns receipt data formatted for thermal printer (80mm paper).

#### 7. Get Receipt PDF
```http
GET /api/v1/sales/{uuid}/receipt/pdf
```

Downloads PDF receipt (80mm x 297mm).

#### 8. Send Receipt
```http
POST /api/v1/sales/{uuid}/receipt/send
```

**Request Body:**
```json
{
  "method": "email",
  "email": "customer@example.com"
}
```

OR

```json
{
  "method": "sms",
  "phone": "+639123456789"
}
```

### Held Transactions

#### 9. Hold Transaction
```http
POST /api/v1/sales/hold
```

**Request Body:**
```json
{
  "name": "John's order",
  "cart_data": {
    "items": [...],
    "customer": {...},
    ...
  }
}
```

Stores cart state for 24 hours.

#### 10. List Held Transactions
```http
GET /api/v1/sales/held/list
```

Returns all active held transactions for current branch.

#### 11. Resume Held Transaction
```http
GET /api/v1/sales/held/{id}/resume
```

Returns cart data and deletes held transaction.

#### 12. Discard Held Transaction
```http
DELETE /api/v1/sales/held/{id}
```

Permanently deletes held transaction.

### Utilities

#### 13. Get Next Sale Number
```http
GET /api/v1/sales/next-number/preview
```

Returns the next sale number for preview (e.g., "INV-2026-000042").

## Sale Creation Process (19 Steps)

The `SaleService::createSale()` method implements a comprehensive 19-step process:

### Phase 1: Validation (Steps 1-3)
1. **Validate Product Ownership**: Ensure all products belong to authenticated user's store
2. **Load Products**: Fetch product data from database
3. **Check Stock**: Validate stock availability using `InventoryService`

### Phase 2: Calculations (Steps 4-8)
4. **Calculate Line Totals**: qty × unit_price - item_discount
5. **Calculate Subtotal**: Sum of all line totals
6. **Apply Order Discount**: Percentage or fixed discount on subtotal
7. **Calculate VAT**:
   - Inclusive: VAT = total × (rate / (100 + rate))
   - Exclusive: VAT = total × (rate / 100), then add to total
8. **Calculate Final Total**: All discounts and VAT applied

### Phase 3: Pre-Transaction Validation (Steps 9-10)
9. **Validate Payments**: Sum of payment amounts must equal total (±1 centavo)
10. **Credit Validation**: If credit payment, check customer credit limit

### Phase 4: Database Transaction (Steps 11-19)
11. **Begin Transaction**: Start database transaction
12. **Generate Sale Number**: Sequential number with format INV-YYYY-XXXXXX
13. **Create Sale Record**: Insert main sale with calculated totals
14. **Create Sale Items**: Insert line items with price snapshots
15. **Create Payments**: Insert payment records
16. **Deduct Inventory**: Update stock levels via `InventoryService`
17. **Handle Credit**: Update customer outstanding balance and create credit transaction
18. **Update Customer**: Increment total_purchases and last_purchase_date
19. **Commit & Return**: Commit transaction, load relationships, fire events

## Monetary Values

**CRITICAL**: All monetary values are stored in **CENTAVOS (integers)** to avoid floating-point precision issues.

### Request (Input)
```json
{
  "unit_price": 28000,  // ₱280.00
  "amount": 250000      // ₱2,500.00
}
```

### Database Storage
```php
// Stored as integers (centavos)
'unit_price' => 28000
'total_amount' => 250000
```

### Response (Output)
```json
{
  "unit_price": "280.00",     // String, 2 decimals
  "total_amount": "2500.00"   // String, 2 decimals
}
```

### Conversion Helpers
```php
// Input: Already in centavos from request
$centavos = $request->input('amount'); // 250000

// Storage: Direct integer storage
Sale::create(['total_amount' => $centavos]);

// Output: Convert to pesos string
number_format($centavos / 100, 2, '.', '') // "2500.00"
```

## VAT Calculation Examples

### VAT-Inclusive (Default in Philippines)

**Scenario**: Product costs ₱112.00 (12% VAT included)

```php
$total = 11200; // centavos
$vatRate = 12;
$vat = $total * (12 / 112) = 1200; // ₱12.00
$netAmount = $total - $vat = 10000; // ₱100.00
```

### VAT-Exclusive

**Scenario**: Product costs ₱100.00 + 12% VAT

```php
$netAmount = 10000; // centavos
$vatRate = 12;
$vat = $netAmount * (12 / 100) = 1200; // ₱12.00
$total = $netAmount + $vat = 11200; // ₱112.00
```

## Discount Calculation

### Item-Level Discount

**Percentage:**
```php
$lineTotal = $quantity * $unitPrice;
$discount = $lineTotal * ($discountValue / 100);
$finalLineTotal = $lineTotal - $discount;
```

**Fixed:**
```php
$lineTotal = $quantity * $unitPrice;
$discount = $discountValue * 100; // Convert to centavos
$finalLineTotal = $lineTotal - $discount;
```

### Order-Level Discount

Applied after item discounts, before VAT:
```php
$subtotal = sum($lineTotals);
$orderDiscount = ($discountType === 'percentage')
    ? $subtotal * ($discountValue / 100)
    : $discountValue * 100;
$subtotalAfterDiscount = $subtotal - $orderDiscount;
```

## Payment Validation

### Total Calculation
```php
$total = calculateTotals($items, $discountType, $discountValue, $vatRate, $vatInclusive);
$paymentSum = array_sum(array_column($payments, 'amount'));
$difference = abs($total - $paymentSum);

// Allow 1 centavo tolerance for rounding
if ($difference > 1) {
    throw new ValidationException('Payment mismatch');
}
```

### Multi-Payment Example
```json
{
  "payments": [
    {"method": "cash", "amount": 150000},
    {"method": "gcash", "amount": 100000}
  ]
}
// Total: ₱2,500.00
```

## Credit Management

### Credit Sale Workflow

1. **Validate Customer**: Must have customer_id
2. **Check Credit Limit**:
```php
$available = $customer->credit_limit - $customer->total_outstanding;
if ($available < $saleAmount) {
    throw ValidationException('Credit limit exceeded');
}
```
3. **Create Sale**: Process normally
4. **Update Outstanding**:
```php
$customer->increment('total_outstanding', $creditAmount);
```
5. **Create Credit Transaction**:
```php
CreditTransaction::create([
    'customer_id' => $customer->id,
    'sale_id' => $sale->id,
    'type' => 'charge',
    'amount' => $creditAmount,
    'balance' => $customer->fresh()->total_outstanding,
]);
```

### Void Credit Sale

```php
// Reverse outstanding balance
$customer->decrement('total_outstanding', $creditPayment->amount);

// Mark transaction as reversed
$creditTransaction->update(['is_reversed' => true]);

// Create reversal record
CreditTransaction::create([
    'type' => 'reversal',
    'amount' => -$creditAmount,
]);
```

## Sale Number Generation

### Format
```
INV-{YEAR}-{SEQUENCE}
```

### Examples
- First sale of 2026: `INV-2026-000001`
- 42nd sale of 2026: `INV-2026-000042`
- First sale of 2027: `INV-2027-000001` (resets yearly)

### Implementation
```php
public function generateSaleNumber($store): string
{
    $year = now()->year;
    $prefix = "INV-{$year}-";

    $lastSale = Sale::where('store_id', $store->id)
        ->where('sale_number', 'LIKE', "{$prefix}%")
        ->lockForUpdate()  // Prevent race conditions
        ->orderBy('sale_number', 'desc')
        ->first();

    $nextNumber = $lastSale
        ? ((int) substr($lastSale->sale_number, -6)) + 1
        : 1;

    return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}
```

## Inventory Integration

### Stock Deduction (Sale)
```php
if ($product->track_inventory) {
    $this->inventoryService->deductStock(
        $product,
        $quantity,
        "Sale #{$saleNumber}",
        $branch
    );
}
```

### Stock Restoration (Void/Refund)
```php
if ($product->track_inventory) {
    $this->inventoryService->restoreStock(
        $product,
        $quantity,
        "Void sale #{$saleNumber}",
        $branch
    );
}
```

### Stock Validation
```php
$validation = $this->inventoryService->validateStockAvailability($items);
if (!$validation['available']) {
    throw ValidationException::withMessages([
        'items' => $validation['errors']
    ]);
}
```

## Events & Listeners

### SaleCompleted Event
Fired after successful sale creation.

**Listeners:**
1. **UpdateCustomerTotals**: Updates customer purchase history and tier
2. **LogSaleActivity**: Creates activity log entry

### SaleVoided Event
Fired after sale is voided.

**Listener:**
- **LogSaleActivity**: Creates void activity log

### Broadcasting
Both events broadcast to:
- `Private Channel: store.{store_id}`
- `Private Channel: branch.{branch_id}`

For real-time POS dashboard updates.

## Error Handling

### Validation Errors (422)
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "payments": ["Payment total does not match sale total."],
    "items.0.quantity": ["Insufficient stock. Available: 5, Requested: 10"]
  }
}
```

### Business Logic Errors
- **Credit Limit Exceeded**: HTTP 422
- **Already Voided**: HTTP 422
- **Insufficient Stock**: HTTP 422
- **Product Not Found**: HTTP 404
- **Unauthorized Access**: HTTP 403

### Transaction Rollback
All multi-step operations use `DB::transaction()`:
```php
DB::transaction(function () {
    // All operations
    // If any fails, entire transaction rolls back
});
```

## Receipt Generation

### Thermal Printer (80mm)

Receipt includes:
- Store information (name, address, TIN, BIR permit)
- Sale details (number, date, cashier)
- Customer information (if applicable)
- Line items with quantities, prices, discounts
- Subtotal, discounts, VAT breakdown
- Total amount
- Payment breakdown
- Change (if cash)
- Footer message

### PDF Receipt

Uses DomPDF with custom blade template:
- Paper size: 80mm × 297mm (thermal roll)
- Monospace font for alignment
- Dashed separators
- Receipt-style formatting

## Security Considerations

### Authentication
All endpoints require:
- `auth:sanctum` middleware
- `store.access` middleware

### Authorization
- Users can only access sales from their store
- Branch-level filtering available
- Role-based access control (implement as needed)

### Data Integrity
- Database transactions prevent partial saves
- Lock for update prevents duplicate sale numbers
- Payment validation prevents over/under payment
- Stock validation prevents overselling

## Performance Optimization

### Eager Loading
```php
Sale::with([
    'customer',
    'items.product',
    'payments',
    'user',
    'branch'
])->get();
```

Prevents N+1 query issues.

### Indexing
Ensure database indexes on:
- `sales.store_id`
- `sales.sale_number`
- `sales.customer_id`
- `sales.sale_date`
- `sales.status`
- `sale_items.sale_id`
- `sale_payments.sale_id`

### Pagination
Always paginate list endpoints:
```php
$sales->paginate($perPage);
```

## Testing Recommendations

### Unit Tests
- `SaleService::calculateTotals()` - All discount/VAT scenarios
- `SaleService::generateSaleNumber()` - Sequence generation
- Payment validation logic
- Credit limit validation

### Feature Tests
- Complete sale creation flow
- Void sale with inventory restoration
- Refund partial and full
- Multi-payment processing
- Held transaction lifecycle

### Edge Cases
- ₱0.01 payment difference tolerance
- Concurrent sale number generation
- Credit limit exactly at threshold
- Products without inventory tracking
- Multiple discounts (item + order level)

## Production Checklist

- [ ] Database migrations run
- [ ] Indexes created
- [ ] EventServiceProvider registered
- [ ] Queue workers running (for async events)
- [ ] Redis configured (for broadcasting)
- [ ] PDF library (DomPDF) installed
- [ ] Receipt printer configured
- [ ] VAT rate configured in store settings
- [ ] Activity logging enabled
- [ ] Backup strategy in place
- [ ] Load testing completed
- [ ] Error monitoring (Sentry, etc.)

## Related Documentation

- [Inventory Management API](./INVENTORY_API_DOCUMENTATION.md)
- [Customer Management API](./CUSTOMER_API_DOCUMENTATION.md)
- [Product Management API](./PRODUCT_API_DOCUMENTATION.md)
- [Credit Transaction API](./CREDIT_API_DOCUMENTATION.md)

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Activity logs: `activity_logs` table
- Database transactions: Enable query logging

---

**Last Updated**: 2026-02-09
**Version**: 1.0.0
**Status**: Production Ready
