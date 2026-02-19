# Reports API - Installation & Setup Guide

## Prerequisites

Before using the Reports API, ensure you have the required dependencies installed.

## Step 1: Install Required Packages

Run the following commands in your project directory:

```bash
# Install DomPDF for PDF generation
composer require barryvdh/laravel-dompdf

# Install Laravel Excel for Excel exports
composer require maatwebsite/excel
```

## Step 2: Publish Configuration Files (Optional)

If you need to customize PDF or Excel settings:

```bash
# Publish DomPDF config
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"

# Publish Laravel Excel config
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

## Step 3: Verify Installation

Check that all files are in place:

```bash
# Check if service exists
ls -la app/Services/ReportService.php

# Check if controller exists
ls -la app/Http/Controllers/Api/ReportController.php

# Check if exports directory exists
ls -la app/Exports/

# Check if views exist
ls -la resources/views/reports/
```

## Step 4: Clear Cache

Clear all caches to ensure new routes and classes are recognized:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

## Step 5: Verify Routes

Check that all report routes are registered:

```bash
php artisan route:list --path=reports
```

You should see 15 routes:
- `/api/v1/reports/sales/daily`
- `/api/v1/reports/sales/summary`
- `/api/v1/reports/sales/by-category`
- `/api/v1/reports/sales/by-customer`
- `/api/v1/reports/sales/by-payment-method`
- `/api/v1/reports/sales/by-cashier`
- `/api/v1/reports/inventory/valuation`
- `/api/v1/reports/inventory/movement`
- `/api/v1/reports/inventory/low-stock`
- `/api/v1/reports/inventory/dead-stock`
- `/api/v1/reports/inventory/profitability`
- `/api/v1/reports/credit/aging`
- `/api/v1/reports/credit/collection`
- `/api/v1/reports/purchases/by-supplier`
- `/api/v1/reports/purchases/price-comparison`

## Step 6: Test Basic Report

Test a simple report to verify everything is working:

```bash
# Using curl (replace YOUR_TOKEN with actual auth token)
curl -X GET "http://localhost/api/v1/reports/inventory/valuation" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

Or use Postman/Insomnia with:
- Method: GET
- URL: `http://localhost/api/v1/reports/inventory/valuation`
- Headers:
  - `Authorization: Bearer YOUR_TOKEN`
  - `Accept: application/json`

## Step 7: Test PDF Export

Test PDF generation:

```bash
curl -X GET "http://localhost/api/v1/reports/inventory/valuation?export=pdf" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output test-report.pdf
```

Open `test-report.pdf` to verify it generated correctly.

## Step 8: Test Excel Export

Test Excel generation:

```bash
curl -X GET "http://localhost/api/v1/reports/inventory/valuation?export=excel" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output test-report.xlsx
```

Open `test-report.xlsx` in Excel/LibreOffice to verify.

## Troubleshooting

### Issue: "Class ReportService not found"

**Solution:**
```bash
composer dump-autoload
php artisan config:clear
```

### Issue: "View [reports.layout] not found"

**Solution:**
Verify the views directory exists:
```bash
ls -la resources/views/reports/
```

If missing, recreate the directory and files.

### Issue: "Class 'Barryvdh\DomPDF\Facade\Pdf' not found"

**Solution:**
```bash
composer require barryvdh/laravel-dompdf
php artisan config:clear
```

### Issue: "Class 'Maatwebsite\Excel\Facades\Excel' not found"

**Solution:**
```bash
composer require maatwebsite/excel
php artisan config:clear
```

### Issue: PDF fonts not rendering correctly

**Solution:**
Edit `config/dompdf.php`:
```php
'font_dir' => storage_path('fonts/'),
'font_cache' => storage_path('fonts/'),
'chroot' => realpath(base_path()),
```

### Issue: Excel export failing silently

**Solution:**
Check storage permissions:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Issue: Memory limit exceeded on large reports

**Solution:**
Increase PHP memory limit in `.env`:
```env
MEMORY_LIMIT=512M
```

Or in `php.ini`:
```ini
memory_limit = 512M
```

### Issue: Timeout on large exports

**Solution:**
Increase max execution time in `php.ini`:
```ini
max_execution_time = 300
```

## Configuration

### DomPDF Configuration

Edit `config/dompdf.php`:

```php
return [
    'show_warnings' => false,
    'orientation' => 'portrait',
    'defines' => [
        'font_dir' => storage_path('fonts/'),
        'font_cache' => storage_path('fonts/'),
        'temp_dir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()),
        'enable_font_subsetting' => false,
        'pdf_backend' => 'CPDF',
        'default_media_type' => 'screen',
        'default_paper_size' => 'letter',
        'default_font' => 'serif',
        'dpi' => 96,
        'enable_php' => false,
        'enable_javascript' => true,
        'enable_remote' => true,
        'font_height_ratio' => 1.1,
        'enable_html5_parser' => true,
    ],
];
```

### Laravel Excel Configuration

Edit `config/excel.php`:

```php
return [
    'exports' => [
        'chunk_size' => 1000,
        'pre_calculate_formulas' => false,
        'strict_null_comparison' => false,
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => false,
            'include_separator_line' => false,
            'excel_compatibility' => false,
        ],
        'properties' => [
            'creator' => 'HardwarePOS',
            'lastModifiedBy' => 'HardwarePOS',
            'title' => 'Report Export',
            'description' => 'Generated by HardwarePOS',
            'subject' => 'Report',
            'keywords' => 'report,export',
            'category' => 'Reports',
            'manager' => '',
            'company' => 'Your Company',
        ],
    ],
];
```

## Performance Optimization

### For Large Datasets

1. **Use Pagination:**
   Add limit parameters to endpoints:
   ```
   /api/v1/reports/sales/by-customer?limit=100
   ```

2. **Use Database Indexes:**
   Ensure indexes exist on:
   - `sales.sale_date`
   - `sales.store_id`
   - `sale_items.product_id`
   - `credit_transactions.transaction_date`
   - `stock_adjustments.created_at`

3. **Consider Caching:**
   ```php
   // In ReportController
   $data = Cache::remember("report-{$key}", 3600, function() {
       return $this->reportService->salesSummary(...);
   });
   ```

4. **Background Processing:**
   For very large exports, use queues:
   ```php
   // Create a job
   dispatch(new GenerateReportJob($reportType, $params));
   ```

## Production Deployment Checklist

- [ ] Install required packages (`dompdf` and `laravel-excel`)
- [ ] Publish and configure package settings
- [ ] Set proper storage permissions
- [ ] Verify all routes are registered
- [ ] Test each report type
- [ ] Test PDF exports
- [ ] Test Excel exports
- [ ] Configure memory limits for production
- [ ] Set up database indexes
- [ ] Configure caching if needed
- [ ] Set up queue workers if using background processing
- [ ] Test with production-like data volumes
- [ ] Configure backup for generated reports (if storing)
- [ ] Set up monitoring for long-running reports

## Next Steps

1. Review the **REPORTS_API_DOCUMENTATION.md** for detailed API usage
2. Test each endpoint with your data
3. Customize PDF templates in `resources/views/reports/` if needed
4. Add custom business logic to `ReportService.php` if required
5. Implement caching for frequently accessed reports
6. Set up scheduled report generation if needed
7. Configure email delivery for automated reports

## Support

For issues or questions:

1. Check the troubleshooting section above
2. Review Laravel logs: `storage/logs/laravel.log`
3. Verify all dependencies are installed
4. Check PHP and web server error logs
5. Ensure database has sufficient data for testing

## Files Summary

**Total Files Created: 35**

- 1 Service class
- 1 Controller
- 15 Export classes
- 16 Blade templates
- 1 Routes update
- 1 Documentation file

All files follow Laravel best practices and HardwarePOS coding standards.
