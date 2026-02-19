# Reports and Analytics API - Complete Documentation

## Overview

The Reports and Analytics API provides comprehensive business intelligence with 15+ report types covering sales, inventory, credit, and purchases with PDF/Excel export capabilities.

## Files Created

### 1. Core Service
- **`app/Services/ReportService.php`** - Main reporting service with all report generation methods

### 2. Controller
- **`app/Http/Controllers/Api/ReportController.php`** - API controller handling all report endpoints

### 3. Export Classes (15 files)
- `app/Exports/DailySalesExport.php`
- `app/Exports/SalesSummaryExport.php`
- `app/Exports/SalesByCategoryExport.php`
- `app/Exports/SalesByCustomerExport.php`
- `app/Exports/SalesByPaymentMethodExport.php`
- `app/Exports/SalesByCashierExport.php`
- `app/Exports/InventoryValuationExport.php`
- `app/Exports/StockMovementExport.php`
- `app/Exports/LowStockExport.php`
- `app/Exports/DeadStockExport.php`
- `app/Exports/ProductProfitabilityExport.php`
- `app/Exports/CreditAgingExport.php`
- `app/Exports/CollectionReportExport.php`
- `app/Exports/PurchasesBySupplierExport.php`
- `app/Exports/PriceComparisonExport.php`

### 4. Blade Templates for PDF (16 files)
- `resources/views/reports/layout.blade.php` - Base layout for all reports
- `resources/views/reports/daily-sales.blade.php`
- `resources/views/reports/sales-summary.blade.php`
- `resources/views/reports/sales-by-category.blade.php`
- `resources/views/reports/sales-by-customer.blade.php`
- `resources/views/reports/sales-by-payment-method.blade.php`
- `resources/views/reports/sales-by-cashier.blade.php`
- `resources/views/reports/inventory-valuation.blade.php`
- `resources/views/reports/stock-movement.blade.php`
- `resources/views/reports/low-stock.blade.php`
- `resources/views/reports/dead-stock.blade.php`
- `resources/views/reports/product-profitability.blade.php`
- `resources/views/reports/credit-aging.blade.php`
- `resources/views/reports/collection-report.blade.php`
- `resources/views/reports/purchases-by-supplier.blade.php`
- `resources/views/reports/price-comparison.blade.php`

### 5. Routes
- Updated `routes/api.php` with 15 new report endpoints

---

## API Endpoints

All endpoints are under `/api/v1/reports/` prefix and require authentication.

### Sales Reports

#### 1. Daily Sales Report
**GET** `/api/v1/reports/sales/daily`

Query Parameters:
- `date` (required) - Date in YYYY-MM-DD format
- `export` (optional) - `pdf` or `excel`

Response:
```json
{
  "success": true,
  "data": {
    "date": "2026-02-10",
    "summary": {
      "transaction_count": 45,
      "total_sales": 15000.00,
      "total_discounts": 250.00,
      "average_transaction": 333.33
    },
    "hourly_breakdown": [
      {
        "hour": 9,
        "transaction_count": 5,
        "total_sales": 1200.00
      }
    ],
    "payment_methods": [...],
    "top_products": [...]
  }
}
```

#### 2. Sales Summary Report
**GET** `/api/v1/reports/sales/summary`

Query Parameters:
- `start_date` (required) - Start date YYYY-MM-DD
- `end_date` (required) - End date YYYY-MM-DD
- `group_by` (optional) - `day`, `week`, or `month` (default: `day`)
- `export` (optional) - `pdf` or `excel`

Response:
```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2026-01-01",
      "end_date": "2026-01-31",
      "group_by": "day"
    },
    "summary": {
      "total_transactions": 234,
      "total_sales": 150000.00,
      "total_discounts": 2500.00,
      "average_transaction": 641.03
    },
    "data": [
      {
        "period": "2026-01-01",
        "transaction_count": 8,
        "total_sales": 4500.00,
        "total_discounts": 100.00,
        "average_transaction": 562.50
      }
    ]
  }
}
```

#### 3. Sales by Category
**GET** `/api/v1/reports/sales/by-category`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2026-01-01",
      "end_date": "2026-01-31"
    },
    "total_sales": 150000.00,
    "data": [
      {
        "category_id": 1,
        "category_name": "Hardware",
        "total_quantity": 150,
        "total_sales": 75000.00,
        "transaction_count": 100,
        "percentage": 50.00
      }
    ]
  }
}
```

#### 4. Sales by Customer
**GET** `/api/v1/reports/sales/by-customer`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `limit` (optional) - Max 500 (default: 50)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "data": [
      {
        "customer": {
          "uuid": "...",
          "code": "CUST001",
          "name": "John Doe",
          "email": "john@example.com",
          "phone": "09123456789"
        },
        "transaction_count": 15,
        "total_purchases": 25000.00,
        "average_order_value": 1666.67,
        "last_purchase_date": "2026-01-30"
      }
    ]
  }
}
```

#### 5. Sales by Payment Method
**GET** `/api/v1/reports/sales/by-payment-method`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "total_amount": 150000.00,
    "data": [
      {
        "method": "cash",
        "transaction_count": 120,
        "total_amount": 90000.00,
        "percentage": 60.00
      },
      {
        "method": "gcash",
        "transaction_count": 80,
        "total_amount": 60000.00,
        "percentage": 40.00
      }
    ]
  }
}
```

#### 6. Sales by Cashier
**GET** `/api/v1/reports/sales/by-cashier`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "data": [
      {
        "cashier": {
          "id": 1,
          "name": "Jane Smith",
          "email": "jane@store.com"
        },
        "transaction_count": 150,
        "total_sales": 100000.00,
        "average_transaction": 666.67
      }
    ]
  }
}
```

### Inventory Reports

#### 7. Inventory Valuation
**GET** `/api/v1/reports/inventory/valuation`

Query Parameters:
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_products": 250,
      "total_units": 5000,
      "total_value": 500000.00
    },
    "data": [
      {
        "category_id": 1,
        "category_name": "Hardware",
        "product_count": 100,
        "total_units": 2000,
        "total_value": 250000.00
      }
    ]
  }
}
```

#### 8. Stock Movement
**GET** `/api/v1/reports/inventory/movement`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `product_id` (optional) - Filter by specific product
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "product_id": null,
    "data": [
      {
        "id": 1,
        "uuid": "...",
        "date": "2026-02-10 10:30:00",
        "product": {
          "id": 1,
          "name": "Product A",
          "sku": "SKU001"
        },
        "branch": {
          "id": 1,
          "name": "Main Branch"
        },
        "type": "sale",
        "quantity_before": 100,
        "quantity_change": -5,
        "quantity_after": 95,
        "reason": "Sale #12345",
        "notes": null,
        "user": {
          "id": 1,
          "name": "Jane Smith"
        }
      }
    ]
  }
}
```

#### 9. Low Stock Alert
**GET** `/api/v1/reports/inventory/low-stock`

Query Parameters:
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "count": 15,
    "data": [
      {
        "uuid": "...",
        "sku": "SKU001",
        "name": "Product A",
        "category": "Hardware",
        "current_stock": 5,
        "reorder_point": 10,
        "minimum_order_qty": 20,
        "unit": "pcs",
        "stock_percentage": 50.00,
        "estimated_days_until_stockout": 3.5,
        "urgency": "high"
      }
    ]
  }
}
```

#### 10. Dead Stock Report
**GET** `/api/v1/reports/inventory/dead-stock`

Query Parameters:
- `days` (optional) - Days without sales (default: 90, max: 365)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period_days": 90,
    "count": 25,
    "total_stock_value": 50000.00,
    "data": [
      {
        "uuid": "...",
        "sku": "SKU100",
        "name": "Dead Product",
        "category": "Tools",
        "current_stock": 50,
        "cost_price": 100.00,
        "stock_value": 5000.00,
        "days_since_last_sale": 120,
        "last_sale_date": "2025-10-10"
      }
    ]
  }
}
```

#### 11. Product Profitability
**GET** `/api/v1/reports/inventory/profitability`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `limit` (optional) - Max 500 (default: 50)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "summary": {
      "total_revenue": 150000.00,
      "total_cost": 90000.00,
      "total_profit": 60000.00
    },
    "data": [
      {
        "product_uuid": "...",
        "product_sku": "SKU001",
        "product_name": "Product A",
        "quantity_sold": 100,
        "total_revenue": 15000.00,
        "total_cost": 9000.00,
        "gross_profit": 6000.00,
        "margin_percentage": 40.00
      }
    ]
  }
}
```

### Credit Reports

#### 12. Credit Aging Report
**GET** `/api/v1/reports/credit/aging`

Query Parameters:
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "summary": {
      "current": 50000.00,
      "days_31_60": 20000.00,
      "days_61_90": 10000.00,
      "days_over_90": 5000.00,
      "total_outstanding": 85000.00
    },
    "customers": [...],
    "customer_count": 25
  }
}
```

#### 13. Collection Report
**GET** `/api/v1/reports/credit/collection`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "summary": {
      "total_collected": 50000.00,
      "total_payments": 75
    },
    "by_method": [
      {
        "payment_method": "cash",
        "payment_count": 50,
        "total_collected": 35000.00
      }
    ],
    "daily_collections": [...]
  }
}
```

### Purchase Reports

#### 14. Purchases by Supplier
**GET** `/api/v1/reports/purchases/by-supplier`

Query Parameters:
- `start_date` (required)
- `end_date` (required)
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "period": {...},
    "summary": {
      "total_amount": 200000.00,
      "total_pos": 50
    },
    "data": [
      {
        "supplier": {
          "code": "SUP001",
          "name": "ABC Supplier",
          "email": "supplier@example.com",
          "phone": "09123456789"
        },
        "po_count": 15,
        "total_amount": 75000.00
      }
    ]
  }
}
```

#### 15. Price Comparison
**GET** `/api/v1/reports/purchases/price-comparison`

Query Parameters:
- `product_id` (optional) - Filter by specific product
- `export` (optional)

Response:
```json
{
  "success": true,
  "data": {
    "product_id": null,
    "data": [
      {
        "product": {
          "uuid": "...",
          "sku": "SKU001",
          "name": "Product A",
          "current_cost_price": 100.00
        },
        "supplier_count": 3,
        "lowest_price": 95.00,
        "highest_price": 110.00,
        "price_variance": 15.00,
        "suppliers": [
          {
            "supplier_code": "SUP001",
            "supplier_name": "ABC Supplier",
            "supplier_sku": "ABC-001",
            "supplier_price": 95.00,
            "lead_time_days": 7,
            "minimum_order_qty": 10,
            "is_preferred": true
          }
        ]
      }
    ]
  }
}
```

---

## Export Functionality

All reports support PDF and Excel exports by adding the `export` query parameter:

### PDF Export
```
GET /api/v1/reports/sales/summary?start_date=2026-01-01&end_date=2026-01-31&export=pdf
```
Returns: Downloadable PDF file

### Excel Export
```
GET /api/v1/reports/sales/summary?start_date=2026-01-01&end_date=2026-01-31&export=excel
```
Returns: Downloadable Excel (.xlsx) file

---

## Key Features

### 1. Optimized Queries
- All reports use database aggregations
- No Eloquent collection processing
- Efficient joins and grouping

### 2. Date Range Validation
- Validates start_date <= end_date
- Maximum 1 year range for summary reports
- Reasonable limits on all date ranges

### 3. Professional Formatting
- All monetary values in pesos (PHP)
- Proper number formatting
- Percentage calculations
- Time-series data

### 4. Performance
- Database-level calculations
- Indexed queries
- Efficient data structures

### 5. Business Intelligence
- Actionable insights
- Trend analysis
- Profitability metrics
- Inventory optimization
- Cash flow tracking

---

## Usage Examples

### Get Today's Sales
```bash
curl -X GET "https://api.example.com/api/v1/reports/sales/daily?date=2026-02-10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Monthly Sales Summary
```bash
curl -X GET "https://api.example.com/api/v1/reports/sales/summary?start_date=2026-01-01&end_date=2026-01-31&group_by=day" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Export Inventory Valuation as PDF
```bash
curl -X GET "https://api.example.com/api/v1/reports/inventory/valuation?export=pdf" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output inventory-valuation.pdf
```

### Low Stock Alert
```bash
curl -X GET "https://api.example.com/api/v1/reports/inventory/low-stock" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Credit Aging Analysis
```bash
curl -X GET "https://api.example.com/api/v1/reports/credit/aging" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Dependencies Required

Make sure these packages are installed:

```bash
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
```

Add to `config/app.php` if not auto-discovered:

```php
'providers' => [
    // ...
    Barryvdh\DomPDF\ServiceProvider::class,
    Maatwebsite\Excel\ExcelServiceProvider::class,
],

'aliases' => [
    // ...
    'PDF' => Barryvdh\DomPDF\Facade\Pdf::class,
    'Excel' => Maatwebsite\Excel\Facades\Excel::class,
],
```

Publish configurations:
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

---

## Error Handling

All endpoints return consistent error responses:

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "start_date": ["The start date field is required."]
  }
}
```

### Date Range Error (422)
```json
{
  "success": false,
  "message": "Date range cannot exceed 1 year"
}
```

### Authentication Error (401)
```json
{
  "message": "Unauthenticated."
}
```

---

## Performance Considerations

1. **Large Datasets**: Reports with large result sets are limited by the `limit` parameter
2. **Date Ranges**: Keep date ranges reasonable (max 1 year for aggregated reports)
3. **Exports**: PDF/Excel generation is done synchronously; for very large reports, consider background processing
4. **Caching**: Consider implementing caching for frequently accessed reports
5. **Indexes**: Ensure proper database indexes on date columns and foreign keys

---

## Future Enhancements

Potential improvements to consider:

1. **Scheduled Reports**: Email reports on schedule
2. **Report Favorites**: Save report configurations
3. **Custom Date Ranges**: Preset ranges (This Week, Last Month, etc.)
4. **Comparative Analysis**: Year-over-year, period-over-period
5. **Visual Charts**: Include chart images in PDF exports
6. **Background Processing**: Queue large export jobs
7. **Report History**: Track generated reports
8. **Custom Filters**: Advanced filtering options
9. **Drill-down Reports**: Click to see detailed data
10. **Report Sharing**: Share reports with team members

---

## Testing

Example test cases to implement:

```php
// Feature Test Example
public function test_can_get_daily_sales_report()
{
    $response = $this->actingAs($user)
        ->getJson('/api/v1/reports/sales/daily?date=2026-02-10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'date',
                'summary',
                'hourly_breakdown',
                'payment_methods',
                'top_products'
            ]
        ]);
}

public function test_validates_date_range()
{
    $response = $this->actingAs($user)
        ->getJson('/api/v1/reports/sales/summary?start_date=2025-01-01&end_date=2026-12-31');

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Date range cannot exceed 1 year'
        ]);
}
```

---

## Summary

The Reports and Analytics API provides:

- **15+ Report Types** covering all business aspects
- **Dual Export Formats** (PDF & Excel)
- **Optimized Performance** with database aggregations
- **Professional Formatting** ready for business use
- **Comprehensive Coverage** of sales, inventory, credit, and purchases
- **Actionable Intelligence** for data-driven decisions

All reports follow consistent patterns, use proper validation, and provide meaningful business insights.
