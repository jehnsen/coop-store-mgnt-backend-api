# Reports API - Quick Reference Guide

## Installation (5 minutes)

```bash
# 1. Install packages
composer require barryvdh/laravel-dompdf maatwebsite/excel

# 2. Clear cache
php artisan config:clear && php artisan route:clear

# 3. Verify routes
php artisan route:list --path=reports
```

---

## All Endpoints at a Glance

### Sales Reports
```
GET /api/v1/reports/sales/daily?date=YYYY-MM-DD&export=pdf|excel
GET /api/v1/reports/sales/summary?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&group_by=day|week|month&export=pdf|excel
GET /api/v1/reports/sales/by-category?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&export=pdf|excel
GET /api/v1/reports/sales/by-customer?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=50&export=pdf|excel
GET /api/v1/reports/sales/by-payment-method?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&export=pdf|excel
GET /api/v1/reports/sales/by-cashier?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&export=pdf|excel
```

### Inventory Reports
```
GET /api/v1/reports/inventory/valuation?export=pdf|excel
GET /api/v1/reports/inventory/movement?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&product_id=123&export=pdf|excel
GET /api/v1/reports/inventory/low-stock?export=pdf|excel
GET /api/v1/reports/inventory/dead-stock?days=90&export=pdf|excel
GET /api/v1/reports/inventory/profitability?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=50&export=pdf|excel
```

### Credit Reports
```
GET /api/v1/reports/credit/aging?export=pdf|excel
GET /api/v1/reports/credit/collection?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&export=pdf|excel
```

### Purchase Reports
```
GET /api/v1/reports/purchases/by-supplier?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&export=pdf|excel
GET /api/v1/reports/purchases/price-comparison?product_id=123&export=pdf|excel
```

---

## Common Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `date` | Date | Yes* | - | Specific date (YYYY-MM-DD) |
| `start_date` | Date | Yes* | - | Range start date |
| `end_date` | Date | Yes* | - | Range end date |
| `group_by` | String | No | day | Grouping: day, week, month |
| `limit` | Integer | No | 50 | Max results (1-500) |
| `days` | Integer | No | 90 | Days threshold (1-365) |
| `product_id` | Integer | No | - | Filter by product |
| `export` | String | No | - | Export format: pdf, excel |

\* Required for specific endpoints

---

## Quick Test Commands

### Test JSON Response
```bash
curl -X GET "http://localhost/api/v1/reports/inventory/valuation" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Download PDF
```bash
curl -X GET "http://localhost/api/v1/reports/sales/summary?start_date=2026-01-01&end_date=2026-01-31&export=pdf" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output report.pdf
```

### Download Excel
```bash
curl -X GET "http://localhost/api/v1/reports/inventory/low-stock?export=excel" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output report.xlsx
```

---

## Response Structure

### Success Response (200)
```json
{
  "success": true,
  "data": {
    "period": {...},
    "summary": {...},
    "data": [...]
  }
}
```

### Error Response (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "start_date": ["The start date field is required."]
  }
}
```

---

## Most Useful Reports

### 1. Today's Sales Performance
```bash
GET /api/v1/reports/sales/daily?date=2026-02-10
```
**Use Case:** End of day reconciliation, cashier performance

### 2. Monthly Sales Trend
```bash
GET /api/v1/reports/sales/summary?start_date=2026-01-01&end_date=2026-01-31&group_by=day
```
**Use Case:** Identify sales patterns, trending analysis

### 3. Inventory Valuation
```bash
GET /api/v1/reports/inventory/valuation
```
**Use Case:** Balance sheet preparation, stock audits

### 4. Low Stock Alert
```bash
GET /api/v1/reports/inventory/low-stock
```
**Use Case:** Reordering, preventing stockouts

### 5. Product Profitability
```bash
GET /api/v1/reports/inventory/profitability?start_date=2026-01-01&end_date=2026-01-31&limit=20
```
**Use Case:** Pricing decisions, product mix optimization

### 6. Credit Aging
```bash
GET /api/v1/reports/credit/aging
```
**Use Case:** Collections, cash flow management

### 7. Top Customers
```bash
GET /api/v1/reports/sales/by-customer?start_date=2026-01-01&end_date=2026-01-31&limit=10
```
**Use Case:** Customer loyalty programs, relationship management

### 8. Dead Stock Analysis
```bash
GET /api/v1/reports/inventory/dead-stock?days=90
```
**Use Case:** Clearance sales, inventory optimization

---

## Export Tips

### When to Use PDF
- ✅ Formal business reports
- ✅ Sharing with management
- ✅ Archiving
- ✅ Printing
- ✅ Email attachments

### When to Use Excel
- ✅ Further data analysis
- ✅ Creating charts
- ✅ Combining with other data
- ✅ Filtering and sorting
- ✅ Custom calculations

---

## Common Use Cases

### Daily Operations
1. **Morning:** Check low stock report
2. **During day:** Monitor today's sales
3. **End of day:** Generate daily sales report
4. **Weekly:** Review dead stock

### Financial Analysis
1. **Weekly:** Sales summary and trends
2. **Monthly:** Profitability analysis
3. **Monthly:** Credit aging review
4. **Quarterly:** Supplier performance

### Inventory Management
1. **Daily:** Low stock alerts
2. **Weekly:** Stock movement review
3. **Monthly:** Inventory valuation
4. **Quarterly:** Dead stock analysis

### Credit Management
1. **Weekly:** Credit aging report
2. **Weekly:** Collection report
3. **Monthly:** Customer statement generation
4. **Ad-hoc:** Overdue accounts follow-up

---

## Performance Guidelines

### Optimal Date Ranges
- **Daily reports:** Single day
- **Summary reports:** 1-3 months
- **Trend analysis:** 3-6 months
- **Annual reports:** Full year (max)

### Result Limits
- **Quick overview:** `limit=10`
- **Standard report:** `limit=50` (default)
- **Comprehensive:** `limit=100`
- **Complete dataset:** `limit=500` (max)

### Best Practices
1. Use specific date ranges (don't query "all time")
2. Apply limit parameters for large datasets
3. Export to Excel for manipulation
4. Use PDF for final reports
5. Cache frequently accessed reports
6. Run large reports during off-peak hours

---

## Troubleshooting Quick Fixes

### Error: Unauthenticated
**Fix:** Add authorization header
```bash
-H "Authorization: Bearer YOUR_TOKEN"
```

### Error: Date range exceeds limit
**Fix:** Reduce date range to max 1 year
```bash
?start_date=2026-01-01&end_date=2026-12-31
```

### Error: Class not found
**Fix:** Clear autoloader
```bash
composer dump-autoload && php artisan config:clear
```

### PDF/Excel not downloading
**Fix:** Verify packages installed
```bash
composer require barryvdh/laravel-dompdf maatwebsite/excel
```

### Memory limit exceeded
**Fix:** Increase PHP memory limit
```ini
; php.ini
memory_limit = 512M
```

---

## File Locations Quick Reference

```
app/
├── Services/
│   └── ReportService.php          ← Main business logic
├── Http/Controllers/Api/
│   └── ReportController.php       ← API endpoints
└── Exports/
    ├── DailySalesExport.php
    ├── SalesSummaryExport.php
    └── ... (15 total)

resources/views/reports/
├── layout.blade.php               ← PDF base layout
├── daily-sales.blade.php
├── sales-summary.blade.php
└── ... (15 total)

routes/
└── api.php                        ← Routes (updated)
```

---

## Next Steps

1. ✅ Install dependencies
2. ✅ Test basic endpoint
3. ✅ Test PDF export
4. ✅ Test Excel export
5. ⬜ Customize templates (if needed)
6. ⬜ Set up caching
7. ⬜ Configure scheduled reports
8. ⬜ Train users on report usage

---

## Support Resources

- **Full Documentation:** `REPORTS_API_DOCUMENTATION.md`
- **Installation Guide:** `REPORTS_INSTALLATION_GUIDE.md`
- **File Summary:** `REPORTS_FILES_SUMMARY.md`
- **Laravel Logs:** `storage/logs/laravel.log`

---

## Quick Wins

Start with these three reports for immediate value:

1. **Low Stock Alert** - Prevent stockouts
   ```
   GET /api/v1/reports/inventory/low-stock
   ```

2. **Daily Sales** - Track daily performance
   ```
   GET /api/v1/reports/sales/daily?date=TODAY
   ```

3. **Credit Aging** - Manage receivables
   ```
   GET /api/v1/reports/credit/aging
   ```

---

**Total Reports:** 15
**Total Endpoints:** 15
**Export Formats:** 2 (PDF + Excel)
**Lines of Code:** ~4,000+
**Time to Install:** 5 minutes
**Time to First Report:** 2 minutes

Happy Reporting! 📊
