# ReportService Quick Reference

## Repository Method Mapping

This document shows which repository methods are used by each ReportService method.

### Sales Reports

| ReportService Method | Repository Methods Used |
|---------------------|------------------------|
| `dailySales()` | `saleRepository->getDailySalesReport()`<br>`saleRepository->getPaymentMethodBreakdown()`<br>`saleRepository->getTopProducts()` |
| `salesSummary()` | `saleRepository->getSalesSummaryGrouped()` |
| `salesByCategory()` | `saleRepository->getSalesByCategory()` |
| `salesByCustomer()` | `saleRepository->getSalesByCustomerReport()` |
| `salesByPaymentMethod()` | `saleRepository->getSalesByPaymentMethod()` |
| `salesByCashier()` | `saleRepository->getSalesByCashier()` |

### Inventory Reports

| ReportService Method | Repository Methods Used |
|---------------------|------------------------|
| `inventoryValuation()` | `productRepository->getInventoryValuationByCategory()` |
| `stockMovement()` | Direct `StockAdjustment` Model (simple query) |
| `lowStockReport()` | `productRepository->getLowStockReport()` |
| `deadStockReport()` | `productRepository->getDeadStockReport()` |
| `productProfitability()` | `productRepository->getProductProfitability()` |

### Credit Reports

| ReportService Method | Repository Methods Used |
|---------------------|------------------------|
| `creditAgingReport()` | `creditService->getAgingReport()` (delegates) |
| `collectionReport()` | `creditTransactionRepository->getCollectionReport()` |

### Purchase Reports

| ReportService Method | Repository Methods Used |
|---------------------|------------------------|
| `purchasesBySupplier()` | `purchaseOrderRepository->getPurchasesBySupplier()` |
| `priceComparisonReport()` | `supplierRepository->getPriceComparisonReport()` |

## New Repository Methods Created

### SaleRepository
```php
// Get daily sales with hourly breakdown
public function getDailySalesReport(Carbon $date): array

// Get sales grouped by period (day/week/month)
public function getSalesSummaryGrouped(Carbon $from, Carbon $to, string $groupBy = 'day'): array

// Get top customers by sales volume
public function getSalesByCustomerReport(Carbon $from, Carbon $to, int $limit = 50): Collection

// Get payment method distribution
public function getPaymentMethodBreakdown(Carbon $from, Carbon $to): array
```

### ProductRepository
```php
// Get inventory value grouped by category
public function getInventoryValuationByCategory(): Collection

// Get low stock products with urgency calculations
public function getLowStockReport(): Collection

// Get products with no sales in X days
public function getDeadStockReport(int $days = 90): Collection

// Get product profitability analysis
public function getProductProfitability(Carbon $from, Carbon $to, int $limit = 50): Collection
```

### SupplierRepository
```php
// Compare prices across suppliers for products
public function getPriceComparisonReport(?int $productId = null): Collection
```

### CreditTransactionRepository
```php
// Enhanced collection report with daily breakdown
public function getCollectionReport(Carbon $from, Carbon $to): array
// Returns: ['summary' => [...], 'by_method' => [...], 'daily_collections' => [...]]
```

## Usage Examples

### In Controllers
```php
use App\Services\ReportService;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function dailySales(Request $request)
    {
        $date = Carbon::parse($request->get('date', today()));
        $report = $this->reportService->dailySales($date);

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    public function salesSummary(Request $request)
    {
        $from = Carbon::parse($request->get('start_date'));
        $to = Carbon::parse($request->get('end_date'));
        $groupBy = $request->get('group_by', 'day'); // 'day', 'week', 'month'

        $report = $this->reportService->salesSummary($from, $to, $groupBy);

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }
}
```

### Direct Repository Usage (if needed)
```php
use App\Repositories\Contracts\SaleRepositoryInterface;

class DashboardService
{
    public function __construct(
        protected SaleRepositoryInterface $saleRepository
    ) {}

    public function getQuickStats()
    {
        // Reuse repository methods
        $todayReport = $this->saleRepository->getDailySalesReport(today());

        return [
            'today_sales' => $todayReport['summary']->total_sales / 100,
            'today_transactions' => $todayReport['summary']->transaction_count,
        ];
    }
}
```

## Return Value Formats

### dailySales()
```php
[
    'date' => '2024-02-11',
    'summary' => [
        'transaction_count' => 45,
        'total_sales' => 125000.50,
        'total_discounts' => 2500.00,
        'average_transaction' => 2777.79
    ],
    'hourly_breakdown' => [
        ['hour' => 9, 'transaction_count' => 5, 'total_sales' => 12500.00],
        // ...
    ],
    'payment_methods' => [
        ['method' => 'cash', 'count' => 30, 'total' => 75000.00],
        // ...
    ],
    'top_products' => [
        ['product_uuid' => '...', 'product_name' => '...', 'quantity_sold' => 50, 'revenue' => 25000.00],
        // ...
    ]
]
```

### salesSummary()
```php
[
    'period' => [
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
        'group_by' => 'day'
    ],
    'summary' => [
        'total_transactions' => 1250,
        'total_sales' => 3500000.00,
        'total_discounts' => 75000.00,
        'average_transaction' => 2800.00
    ],
    'data' => [
        ['period' => '2024-01-01', 'transaction_count' => 45, 'total_sales' => 125000.00, ...],
        // ...
    ]
]
```

### inventoryValuation()
```php
[
    'summary' => [
        'total_products' => 1500,
        'total_units' => 25000,
        'total_value' => 2500000.00
    ],
    'data' => [
        [
            'category_id' => 1,
            'category_name' => 'Tools',
            'product_count' => 250,
            'total_units' => 5000,
            'total_value' => 500000.00
        ],
        // ...
    ]
]
```

### lowStockReport()
```php
[
    'count' => 25,
    'data' => [
        [
            'uuid' => '...',
            'sku' => 'PROD-001',
            'name' => 'Hammer',
            'category' => 'Tools',
            'current_stock' => 5,
            'reorder_point' => 20,
            'minimum_order_qty' => 50,
            'unit' => 'pcs',
            'stock_percentage' => 25.00,
            'estimated_days_until_stockout' => 7.5,
            'urgency' => 'high' // 'critical', 'high', 'medium'
        ],
        // ...
    ]
]
```

### productProfitability()
```php
[
    'period' => [
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31'
    ],
    'summary' => [
        'total_revenue' => 3500000.00,
        'total_cost' => 2100000.00,
        'total_profit' => 1400000.00
    ],
    'data' => [
        [
            'product_uuid' => '...',
            'product_sku' => 'PROD-001',
            'product_name' => 'Hammer',
            'quantity_sold' => 500,
            'total_revenue' => 250000.00,
            'total_cost' => 150000.00,
            'gross_profit' => 100000.00,
            'margin_percentage' => 40.00
        ],
        // ...
    ]
]
```

### collectionReport()
```php
[
    'period' => [
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31'
    ],
    'summary' => [
        'total_collected' => 500000.00,
        'total_payments' => 125
    ],
    'by_method' => [
        ['payment_method' => 'cash', 'payment_count' => 80, 'total_collected' => 300000.00],
        ['payment_method' => 'bank_transfer', 'payment_count' => 45, 'total_collected' => 200000.00]
    ],
    'daily_collections' => [
        ['date' => '2024-01-01', 'payment_method' => 'cash', 'payment_count' => 3, 'total_collected' => 15000.00],
        // ...
    ]
]
```

## Performance Tips

### 1. Use Appropriate Date Ranges
```php
// Good - specific date range
$report = $reportService->salesSummary(
    Carbon::parse('2024-01-01'),
    Carbon::parse('2024-01-31'),
    'day'
);

// Better for large datasets - use week/month grouping
$report = $reportService->salesSummary(
    Carbon::parse('2023-01-01'),
    Carbon::parse('2023-12-31'),
    'month' // Reduces data points from 365 to 12
);
```

### 2. Limit Result Sets
```php
// Use limit parameters
$topCustomers = $reportService->salesByCustomer($from, $to, 20); // Top 20 only
$topProducts = $reportService->productProfitability($from, $to, 50); // Top 50 only
```

### 3. Cache Expensive Reports
```php
use Illuminate\Support\Facades\Cache;

$yearlyReport = Cache::remember('sales_summary_2023', 3600, function() {
    return $reportService->salesSummary(
        Carbon::parse('2023-01-01'),
        Carbon::parse('2023-12-31'),
        'month'
    );
});
```

## Testing Examples

### Unit Test for Repository
```php
public function test_getDailySalesReport_returns_correct_structure()
{
    // Arrange
    $date = Carbon::parse('2024-01-15');

    // Act
    $result = $this->saleRepository->getDailySalesReport($date);

    // Assert
    $this->assertIsArray($result);
    $this->assertArrayHasKey('hourly_breakdown', $result);
    $this->assertArrayHasKey('summary', $result);
}
```

### Integration Test for Service
```php
public function test_dailySales_formats_currency_correctly()
{
    // Arrange
    Sale::factory()->create([
        'total_amount' => 10000, // 100.00 in centavos
        'sale_date' => now()
    ]);

    // Act
    $report = $this->reportService->dailySales(today());

    // Assert
    $this->assertEquals(100.00, $report['summary']['total_sales']);
}
```

## Troubleshooting

### Issue: Report returns empty data
**Solution**: Check date range format and ensure data exists for the period
```php
// Verify date parsing
$date = Carbon::parse($request->get('date'));
Log::info('Report date:', ['date' => $date->toDateTimeString()]);

// Check for data
$count = Sale::whereDate('sale_date', $date)->count();
Log::info('Sales found:', ['count' => $count]);
```

### Issue: Performance is slow
**Solution**: Check query execution and add indexes
```php
// Enable query logging
DB::enableQueryLog();
$report = $reportService->salesSummary($from, $to);
dd(DB::getQueryLog());

// Add indexes on frequently queried columns
Schema::table('sales', function (Blueprint $table) {
    $table->index(['store_id', 'sale_date', 'status']);
});
```

### Issue: Memory limit exceeded
**Solution**: Use chunking for large datasets or implement background processing
```php
// For very large reports, consider queuing
dispatch(new GenerateYearlyReport($year));
```
