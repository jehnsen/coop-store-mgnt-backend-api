# ReceiptService & PDF Generation Documentation

## Overview

A comprehensive receipt generation service has been created for the HardwarePOS system. This service handles PDF generation for sales receipts, delivery receipts, and customer statements using Laravel DomPDF.

---

## Files Created

### 1. ReceiptService
**Location**: `app/Services/ReceiptService.php`

### 2. Receipt View Templates
**Location**: `resources/views/receipts/`

- `standard.blade.php` - Standard A4 size receipt
- `thermal.blade.php` - 80mm thermal printer receipt
- `delivery.blade.php` - Delivery receipt
- `customer-statement.blade.php` - Customer statement of account

---

## ReceiptService Methods

### Sales Receipts

#### `generateReceiptData(Sale $sale): array`
Generates complete receipt data array for a sale.

**Returns:**
```php
[
    'store' => [...],           // Store information
    'branch' => [...],          // Branch information
    'sale' => [...],            // Sale details
    'customer' => [...],        // Customer info (if any)
    'cashier' => [...],         // Cashier details
    'items' => [...],           // Sale items
    'refunded_items' => [...],  // Refunded items (if any)
    'pricing' => [...],         // Pricing breakdown
    'payments' => [...],        // Payment details
    'refund_payments' => [...], // Refund payments (if any)
    'totals' => [...],          // Summary totals
    'header_text' => '...',     // Receipt header
    'footer_text' => '...',     // Receipt footer
    'is_voided' => false,
    'is_refunded' => false,
    'has_refunds' => false,
    'generated_at' => '...',
]
```

#### `generatePDF(Sale $sale, string $size = 'a4'): PDF`
Generates PDF receipt for a sale.

**Parameters:**
- `$sale` - Sale model instance
- `$size` - Receipt size: `'a4'` (standard) or `'thermal'` (80mm)

**Usage:**
```php
use App\Services\ReceiptService;

$receiptService = app(ReceiptService::class);

// Standard A4 receipt
$pdf = $receiptService->generatePDF($sale);
return $pdf->download('receipt-' . $sale->sale_number . '.pdf');

// Thermal printer receipt (80mm)
$pdf = $receiptService->generatePDF($sale, 'thermal');
return $pdf->stream();
```

### Delivery Receipts

#### `generateDeliveryReceiptData(Delivery $delivery): array`
Generates delivery receipt data.

**Returns:**
```php
[
    'store' => [...],
    'delivery' => [...],
    'customer' => [...],
    'driver' => [...],
    'sale' => [...],          // Related sale (if any)
    'items' => [...],
    'has_proof' => false,
    'proof_image_path' => null,
    'generated_at' => '...',
]
```

#### `generateDeliveryPDF(Delivery $delivery): PDF`
Generates PDF delivery receipt.

**Usage:**
```php
$pdf = $receiptService->generateDeliveryPDF($delivery);
return $pdf->download('delivery-' . $delivery->delivery_number . '.pdf');
```

### Customer Statements

#### `generateCustomerStatementData(Customer $customer, string $startDate, string $endDate): array`
Generates customer statement data with aging analysis.

**Parameters:**
- `$customer` - Customer model instance
- `$startDate` - Start date (YYYY-MM-DD)
- `$endDate` - End date (YYYY-MM-DD)

**Returns:**
```php
[
    'store' => [...],
    'customer' => [...],
    'statement' => [
        'start_date' => '...',
        'end_date' => '...',
        'opening_balance' => '...',
        'total_charges' => '...',
        'total_payments' => '...',
        'closing_balance' => '...',
    ],
    'transactions' => [...],
    'aging' => [
        'current' => '...',      // 0-30 days
        '31_60' => '...',        // 31-60 days
        '61_90' => '...',        // 61-90 days
        'over_90' => '...',      // Over 90 days
        'total' => '...',
    ],
    'generated_at' => '...',
]
```

#### `generateCustomerStatementPDF(Customer $customer, string $startDate, string $endDate): PDF`
Generates PDF customer statement.

**Usage:**
```php
$pdf = $receiptService->generateCustomerStatementPDF(
    $customer,
    '2024-01-01',
    '2024-12-31'
);
return $pdf->download('statement-' . $customer->customer_code . '.pdf');
```

### Helper Methods

#### `sendReceiptEmail(Sale $sale, string $email): bool`
Send receipt via email (TODO: Implementation pending).

#### `sendReceiptSMS(Sale $sale, string $phone): bool`
Send receipt via SMS (TODO: Implementation pending).

---

## Controller Implementation Examples

### Sales Receipt Endpoint

```php
// In SaleController.php

use App\Services\ReceiptService;

public function getReceipt(Sale $sale)
{
    $receiptService = app(ReceiptService::class);
    $data = $receiptService->generateReceiptData($sale);

    return response()->json([
        'success' => true,
        'data' => $data,
    ]);
}

public function downloadReceiptPDF(Sale $sale, Request $request)
{
    $receiptService = app(ReceiptService::class);

    // Get size from query parameter (default: a4)
    $size = $request->query('size', 'a4');

    // Validate size
    if (!in_array($size, ['a4', 'thermal'])) {
        $size = 'a4';
    }

    $pdf = $receiptService->generatePDF($sale, $size);

    return $pdf->download('receipt-' . $sale->sale_number . '.pdf');
}

public function streamReceiptPDF(Sale $sale, Request $request)
{
    $receiptService = app(ReceiptService::class);
    $size = $request->query('size', 'a4');

    $pdf = $receiptService->generatePDF($sale, $size);

    return $pdf->stream();
}

public function sendReceipt(Sale $sale, Request $request)
{
    $validated = $request->validate([
        'method' => 'required|in:email,sms',
        'recipient' => 'required|string',
    ]);

    $receiptService = app(ReceiptService::class);

    if ($validated['method'] === 'email') {
        $success = $receiptService->sendReceiptEmail($sale, $validated['recipient']);
    } else {
        $success = $receiptService->sendReceiptSMS($sale, $validated['recipient']);
    }

    return response()->json([
        'success' => $success,
        'message' => $success ? 'Receipt sent successfully' : 'Failed to send receipt',
    ]);
}
```

### Delivery Receipt Endpoint

```php
// In DeliveryController.php

public function getDeliveryReceipt(Delivery $delivery)
{
    $receiptService = app(ReceiptService::class);
    $data = $receiptService->generateDeliveryReceiptData($delivery);

    return response()->json([
        'success' => true,
        'data' => $data,
    ]);
}

public function downloadDeliveryReceiptPDF(Delivery $delivery)
{
    $receiptService = app(ReceiptService::class);
    $pdf = $receiptService->generateDeliveryPDF($delivery);

    return $pdf->download('delivery-' . $delivery->delivery_number . '.pdf');
}
```

### Customer Statement Endpoint

```php
// In CustomerController.php

public function getStatement(Customer $customer, Request $request)
{
    $validated = $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
    ]);

    $receiptService = app(ReceiptService::class);

    $data = $receiptService->generateCustomerStatementData(
        $customer,
        $validated['start_date'],
        $validated['end_date']
    );

    return response()->json([
        'success' => true,
        'data' => $data,
    ]);
}

public function downloadStatementPDF(Customer $customer, Request $request)
{
    $validated = $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
    ]);

    $receiptService = app(ReceiptService::class);

    $pdf = $receiptService->generateCustomerStatementPDF(
        $customer,
        $validated['start_date'],
        $validated['end_date']
    );

    return $pdf->download('statement-' . $customer->customer_code . '.pdf');
}
```

---

## Route Examples

Add these routes to `routes/api.php`:

```php
// Sales Receipt Routes
Route::prefix('sales')->middleware(['auth:sanctum', 'store.access'])->group(function () {
    Route::get('/{sale}/receipt', [SaleController::class, 'getReceipt']);
    Route::get('/{sale}/receipt/pdf', [SaleController::class, 'downloadReceiptPDF']);
    Route::get('/{sale}/receipt/stream', [SaleController::class, 'streamReceiptPDF']);
    Route::post('/{sale}/receipt/send', [SaleController::class, 'sendReceipt']);
});

// Delivery Receipt Routes
Route::prefix('deliveries')->middleware(['auth:sanctum', 'store.access'])->group(function () {
    Route::get('/{delivery}/receipt', [DeliveryController::class, 'getDeliveryReceipt']);
    Route::get('/{delivery}/receipt/pdf', [DeliveryController::class, 'downloadDeliveryReceiptPDF']);
});

// Customer Statement Routes
Route::prefix('customers')->middleware(['auth:sanctum', 'store.access'])->group(function () {
    Route::get('/{customer}/statement', [CustomerController::class, 'getStatement']);
    Route::get('/{customer}/statement/pdf', [CustomerController::class, 'downloadStatementPDF']);
});
```

---

## Receipt Templates

### Standard Receipt (A4)
**File**: `resources/views/receipts/standard.blade.php`

**Features:**
- Professional A4 size layout (210mm x 297mm)
- Store logo display
- Complete store and branch information
- VAT registration details (TIN, BIR Permit)
- Customer information section
- Itemized product list with discounts
- VAT breakdown (for VAT-registered stores)
- Payment details with multiple payment methods
- Refund support with watermark
- Void support with watermark and reason
- BIR-compliant footer

**Best for:**
- Formal invoicing
- Corporate customers
- Government customers
- Record keeping
- Printing on standard paper

### Thermal Receipt (80mm)
**File**: `resources/views/receipts/thermal.blade.php`

**Features:**
- Optimized for 80mm thermal printers
- Compact design with minimal margins
- Essential information only
- Quick print speed
- Low ink/toner usage
- Space-efficient layout

**Best for:**
- Point of Sale printing
- Walk-in customers
- Quick transactions
- Thermal printer hardware
- Cost-effective printing

### Delivery Receipt
**File**: `resources/views/receipts/delivery.blade.php`

**Features:**
- Professional delivery document
- Customer delivery address
- Driver information
- Item list with quantities
- Status badges (pending, in-transit, delivered)
- Signature sections (3 signatures)
  - Prepared by
  - Delivered by (driver)
  - Received by (customer)
- Proof of delivery image display
- Related sales invoice reference

**Best for:**
- Delivery tracking
- Proof of delivery
- Customer acknowledgment
- Logistics documentation

### Customer Statement
**File**: `resources/views/receipts/customer-statement.blade.php`

**Features:**
- Complete account statement
- Opening and closing balances
- Transaction history with dates
- Charges and payments breakdown
- Aging analysis (4 buckets)
  - Current (0-30 days)
  - 31-60 days
  - 61-90 days
  - Over 90 days
- Overdue warning alerts
- Payment instructions
- Professional layout for credit customers

**Best for:**
- Credit management
- Customer account review
- Collection purposes
- Month-end statements
- Aging analysis

---

## Styling & Customization

### Standard Receipt Styling

The standard receipt uses inline CSS for maximum PDF compatibility. Key style features:

- **Monospace font** for receipt authenticity
- **Grid layout** for structured information
- **Table styling** for items
- **Watermarks** for voided/refunded sales
- **Color coding**: Red for discounts/refunds, green for payments

### Customization Options

You can customize receipts by modifying:

1. **Store Settings** (via database):
   - `receipt_header` - Header text
   - `receipt_footer` - Footer text
   - `logo_path` - Store logo
   - `vat_rate` - VAT percentage

2. **View Templates** (Blade files):
   - Layout and styling
   - Additional fields
   - Language/text
   - Branding elements

---

## BIR Compliance Features

The receipts include Philippine BIR (Bureau of Internal Revenue) compliance features:

✅ **VAT Registration Display**
- TIN (Tax Identification Number)
- BIR Permit Number
- VAT rate (12%)

✅ **VAT Breakdown**
- VATable Sales
- VAT Amount
- VAT-Exempt Sales (if applicable)
- Total Amount

✅ **Required Footer Text**
- "This invoice/receipt shall be valid for five (5) years from the date of the permit to use."

✅ **Proper Receipt Format**
- Sequential numbering
- Date and time
- Complete store information
- Itemized list

---

## PDF Generation Tips

### Performance Optimization

```php
// 1. Eager load relationships to avoid N+1 queries
$sale = Sale::with([
    'store',
    'branch',
    'customer',
    'user',
    'items.product.unit',
    'payments'
])->findOrFail($uuid);

$pdf = $receiptService->generatePDF($sale);

// 2. Stream instead of download for faster display
return $pdf->stream(); // Displays in browser
// vs
return $pdf->download(); // Forces download

// 3. Cache generated PDFs for frequently accessed receipts
$cacheKey = "receipt-pdf-{$sale->uuid}";
$pdf = Cache::remember($cacheKey, now()->addHours(24), function () use ($sale, $receiptService) {
    return $receiptService->generatePDF($sale)->output();
});

return response($pdf, 200, [
    'Content-Type' => 'application/pdf',
]);
```

### PDF Options

```php
// Custom page size
$pdf->setPaper('a4', 'portrait');
$pdf->setPaper('letter', 'landscape');

// Custom margins
$pdf->setOptions([
    'margin-top' => 10,
    'margin-right' => 10,
    'margin-bottom' => 10,
    'margin-left' => 10,
]);

// Enable remote images (if using external logo URLs)
$pdf->setOptions(['isRemoteEnabled' => true]);
```

---

## Testing Receipts

### Manual Testing

```php
// In tinker or test file
php artisan tinker

use App\Models\Sale;
use App\Services\ReceiptService;

$sale = Sale::with(['store', 'branch', 'customer', 'items.product', 'payments'])->first();
$receiptService = app(ReceiptService::class);

// Test data generation
$data = $receiptService->generateReceiptData($sale);
dd($data);

// Test PDF generation
$pdf = $receiptService->generatePDF($sale);
$pdf->save(storage_path('app/test-receipt.pdf'));

// Test thermal receipt
$pdf = $receiptService->generatePDF($sale, 'thermal');
$pdf->save(storage_path('app/test-thermal-receipt.pdf'));
```

### Unit Test Example

```php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Sale;
use App\Services\ReceiptService;

class ReceiptServiceTest extends TestCase
{
    public function test_generate_receipt_data()
    {
        $sale = Sale::factory()->create();
        $service = app(ReceiptService::class);

        $data = $service->generateReceiptData($sale);

        $this->assertArrayHasKey('store', $data);
        $this->assertArrayHasKey('sale', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pricing', $data);
    }

    public function test_generate_pdf()
    {
        $sale = Sale::factory()->create();
        $service = app(ReceiptService::class);

        $pdf = $service->generatePDF($sale);

        $this->assertNotNull($pdf);
        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }
}
```

---

## Future Enhancements

### Planned Features

1. **Email Integration**
   - Complete `sendReceiptEmail()` implementation
   - Use Laravel Mail with Mailable classes
   - Attach PDF to email
   - Support HTML email templates

2. **SMS Integration**
   - Complete `sendReceiptSMS()` implementation
   - Integrate with SMS providers (Semaphore, Twilio)
   - Send receipt summary via SMS
   - Include download link

3. **Additional Receipt Types**
   - Purchase Order receipts
   - Stock adjustment receipts
   - Credit memo
   - Debit memo

4. **Localization**
   - Multi-language support
   - Currency localization
   - Date format preferences

5. **QR Code**
   - Add QR code to receipts
   - Link to online receipt verification
   - Customer feedback URL

---

## Troubleshooting

### Common Issues

**Issue: "Class 'DomPDF' not found"**
```bash
# Solution: Clear config cache
php artisan config:clear
composer dump-autoload
```

**Issue: Images not showing in PDF**
```php
// Solution: Use absolute paths or public URLs
$logoPath = public_path('storage/logos/logo.png');
// or
$logoPath = asset('storage/logos/logo.png');
```

**Issue: PDF layout broken**
```php
// Solution: Check for:
// 1. Invalid HTML structure
// 2. Unsupported CSS (DomPDF has limited CSS support)
// 3. Missing closing tags
// 4. Use inline CSS instead of external stylesheets
```

**Issue: Slow PDF generation**
```php
// Solution:
// 1. Eager load all relationships
// 2. Cache generated PDFs
// 3. Optimize images (compress before storing)
// 4. Consider using Queue for large PDFs
```

---

## Dependencies

- **barryvdh/laravel-dompdf** ^3.1 - PDF generation
- **Laravel 11.x** - Framework
- **PHP 8.2+** - Required for Laravel 11

---

## License

This ReceiptService is part of the HardwarePOS project. All rights reserved.

---

**Created**: February 2024
**Last Updated**: February 2024
**Version**: 1.0.0
