# HardwarePOS - Cloud-based Point of Sale Backend

## Project Overview

**HardwarePOS** is a comprehensive cloud-based Point of Sale (POS) system designed specifically for hardware stores in the Philippines. This Laravel-based backend API provides complete retail management functionality including sales, inventory, customer credit management, purchase orders, deliveries, and business analytics.

### Key Information
- **Framework**: Laravel 11.x
- **Authentication**: Laravel Sanctum (Token-based API authentication)
- **Database**: MySQL
- **Currency**: Philippine Peso (PHP) - stored as centavos (integers)
- **Timezone**: Asia/Manila
- **API Version**: v1
- **API Prefix**: `/api/v1`

---

## Architecture & Design Patterns

### Multi-Tenancy
The system implements **store-level multi-tenancy**:
- Each user belongs to a specific store
- All data is automatically filtered by `store_id` via the `store.access` middleware
- Complete data isolation between stores
- No manual filtering required in controllers or queries

### Middleware Stack
1. **auth:sanctum** - API token authentication
2. **store.access** - Automatic store_id injection and filtering
3. **CheckPermission** - Role-based permission validation
4. **EnsureBranchAccess** - Branch-level access control
5. **LogActivity** - Activity logging via Spatie Laravel Activity Log

### Money Handling
**Critical Convention**: All monetary values are stored as **CENTAVOS (integers)** for precision:
- 1 Peso = 100 centavos
- ₱250.00 = 25,000 centavos
- ₱10,000.00 = 1,000,000 centavos

This prevents floating-point precision errors in financial calculations.

---

## Technology Stack

### Core Dependencies
```json
{
    "laravel/framework": "^11.0",
    "laravel/sanctum": "^4.0",
    "dedoc/scramble": "^0.11"
}
```

**Note:** DomPDF and Laravel Excel packages may be installed but are not used. All PDF/Excel generation is handled by the frontend.

### Key Packages
- **Laravel Sanctum** - API authentication with tokens
- **Scramble** - API documentation generation
- **Spatie Activity Log** - Audit trail and activity logging

### Architecture Note
This is a **pure JSON API backend**. All PDF generation, Excel exports, and UI rendering are handled by the frontend application. The backend returns structured JSON data only.

---

## Database Structure

### Core Models & Relationships

#### Store & Users
- **Store** - Multi-tenant store entity
  - Has many: Users, Products, Sales, Customers, etc.
- **User** - System users (admin, manager, cashier, etc.)
  - Belongs to: Store, Branch
  - Has many: Sales (as cashier), ActivityLogs
  - Uses: Laravel Sanctum for API tokens

#### Products & Inventory
- **Product** - Product catalog
  - Belongs to: Store, Category, UnitOfMeasure
  - Has many: SaleItems, PurchaseOrderItems, StockAdjustments
  - Fields: uuid, name, sku, barcode, cost_price, retail_price, current_stock, reorder_point
- **Category** - Product categories
  - Belongs to: Store
  - Has many: Products
- **UnitOfMeasure** - Units (pcs, box, kg, etc.)
  - Has many: Products

#### Sales & POS
- **Sale** - Sales transactions
  - Belongs to: Store, Customer (optional), Cashier (User), Branch
  - Has many: SaleItems, Payments
  - Fields: uuid, sale_number, subtotal, tax, total, status, payment_method
  - Statuses: completed, voided, refunded
- **SaleItem** - Line items in sale
  - Belongs to: Sale, Product
  - Fields: quantity, price, subtotal
- **HeldTransaction** - Paused POS transactions
  - Belongs to: Store, User
  - Can be resumed or discarded

#### Customers & Credit
- **Customer** - Customer records
  - Belongs to: Store
  - Has many: Sales, CreditTransactions, Deliveries
  - Fields: uuid, name, type, credit_limit, credit_terms_days, current_balance
  - Types: walk_in, regular, contractor, government
- **CreditTransaction** - Credit ledger entries
  - Belongs to: Store, Customer, Sale (optional)
  - Types: charge, payment, adjustment
  - Payment allocation: FIFO (First In, First Out)

#### Supply Chain & Accounts Payable
- **Supplier** - Supplier management
  - Belongs to: Store
  - Has many: PurchaseOrders, PayableTransactions, SupplierProducts
  - AP Fields: total_outstanding, total_purchases, payment_rating
- **PurchaseOrder** - Purchase orders
  - Belongs to: Store, Supplier
  - Has many: PurchaseOrderItems, PayableTransactions
  - Statuses: draft, submitted, received, cancelled
  - Payment tracking: payment_status, amount_paid, payment_due_date
- **PayableTransaction** - Accounts payable ledger
  - Belongs to: Store, Supplier, PurchaseOrder (optional), User
  - Types: invoice, payment, adjustment
  - Payment allocation: FIFO (First In, First Out)
  - Fields: uuid, amount, balance_before, balance_after, transaction_date, due_date, paid_date
- **Delivery** - Delivery tracking
  - Belongs to: Store, Customer, Sale (optional), Driver (User)
  - Has many: DeliveryItems
  - Statuses: pending, in_transit, delivered, cancelled
  - Features: Proof of delivery (photo/signature)

#### Settings & Configuration
- **Branch** - Store branches for multi-location support
- **Permission** - Role-based access control
- **StoreSettings** - Store profile, tax settings, receipt templates, etc.

---

## API Endpoints Overview

### Complete API Structure (160+ endpoints)

#### 1. Authentication (`/auth`)
- POST `/login` - User authentication (returns Bearer token)
- POST `/logout` - Invalidate current token
- GET `/me` - Get authenticated user
- PUT `/profile` - Update user profile
- POST `/change-password` - Change password
- POST `/forgot-password` - Request password reset
- POST `/reset-password` - Complete password reset

#### 2. Products (`/products`)
- Standard CRUD: GET, POST, PUT, DELETE
- GET `/search` - Fast search for POS (by name/SKU/barcode)
- GET `/barcode/{barcode}` - Barcode lookup
- GET `/low-stock` - Products below reorder point
- POST `/{uuid}/adjust-stock` - Stock adjustments
- GET `/{uuid}/stock-history` - Stock movement history
- POST `/bulk-update` - Bulk update multiple products

#### 3. Categories (`/categories`)
- Standard CRUD operations
- POST `/reorder` - Reorder categories

#### 4. Units (`/units`)
- Standard CRUD for units of measure

#### 5. Sales - POS (`/sales`)
- Standard CRUD with status filters
- POST `/` - Create sale (main POS transaction)
- POST `/{uuid}/void` - Void sale
- POST `/{uuid}/refund` - Full or partial refund
- GET `/{uuid}/receipt` - Get receipt data (JSON)
- POST `/hold` - Hold/pause transaction
- GET `/held/list` - List held transactions
- GET `/held/{id}/resume` - Resume held transaction
- DELETE `/held/{id}` - Discard held transaction
- GET `/next-number/preview` - Preview next sale number

#### 6. Customers (`/customers`)
- Standard CRUD with advanced search
- GET `/{uuid}/transactions` - Credit transaction history
- POST `/{uuid}/payments` - Record payment (auto-allocates FIFO)
- PUT `/{uuid}/credit-limit` - Adjust credit limit
- GET `/{uuid}/statement` - Customer statement (JSON)
- GET `/credit/overview` - Credit statistics (AR)
- GET `/credit/aging` - Aging analysis (4 buckets)
- GET `/credit/overdue` - Overdue accounts

#### 7. Dashboard (`/dashboard`)
- GET `/summary` - Today's summary stats
- GET `/sales-trend?days=30` - Sales trend analysis
- GET `/top-products?limit=10` - Top selling products
- GET `/sales-by-category` - Category breakdown
- GET `/credit-aging` - Credit aging summary
- GET `/recent-transactions` - Recent sales
- GET `/stock-alerts` - Low stock warnings
- GET `/upcoming-deliveries` - Delivery schedule
- GET `/top-customers` - Top customers by sales
- GET `/comprehensive` - Full dashboard (all data in one call)

#### 8. Suppliers (`/suppliers`)
- Standard CRUD operations
- GET `/{uuid}/products` - Supplier's products
- POST `/{uuid}/products` - Link product to supplier
- DELETE `/{uuid}/products/{productUuid}` - Unlink product
- GET `/{uuid}/price-history` - Price history

#### 9. Accounts Payable (`/ap`)
**Dashboard & Reports:**
- GET `/overview` - AP statistics and overview
- GET `/aging` - 4-bucket aging analysis (current, 31-60, 61-90, over-90)
- GET `/overdue` - Suppliers with overdue invoices
- GET `/payment-schedule` - Upcoming payments due
- GET `/disbursement-report` - Payments made report

**Supplier-Specific AP:**
- GET `/suppliers/{uuid}/payables` - Payable transactions (JSON)
- GET `/suppliers/{uuid}/ledger` - AP ledger (alias)
- POST `/suppliers/{uuid}/payments` - Make payment (FIFO allocation)
- GET `/suppliers/{uuid}/statement` - Supplier statement (JSON)

**Features:**
- Automatic AP invoice creation when PO is received
- FIFO payment allocation to oldest invoices
- 4-bucket aging analysis
- Complete audit trail

#### 10. Purchase Orders (`/purchase-orders`)
- Standard CRUD with workflow
- POST `/{uuid}/submit` - Submit for approval
- POST `/{uuid}/receive` - Receive PO (updates stock automatically, creates AP invoice)
- POST `/{uuid}/cancel` - Cancel PO

#### 11. Deliveries (`/deliveries`)
- GET `/today-schedule` - Today's deliveries
- Standard CRUD operations
- PUT `/{uuid}/status` - Update status
- POST `/{uuid}/proof` - Upload proof of delivery
- GET `/{uuid}/proof/download` - Download proof
- GET `/{uuid}/receipt` - Delivery receipt data (JSON)
- POST `/{uuid}/assign-driver` - Assign driver

#### 12. Reports (`/reports`)

**Sales Reports:**
- GET `/sales/daily?date=YYYY-MM-DD`
- GET `/sales/summary?start_date=&end_date=&group_by=month`
- GET `/sales/by-category`
- GET `/sales/by-customer`
- GET `/sales/by-payment-method`
- GET `/sales/by-cashier`

**Inventory Reports:**
- GET `/inventory/valuation`
- GET `/inventory/movement`
- GET `/inventory/low-stock`
- GET `/inventory/dead-stock?days=90`
- GET `/inventory/profitability`

**Credit Reports:**
- GET `/credit/aging`
- GET `/credit/collection`

**Purchase Reports:**
- GET `/purchases/by-supplier`
- GET `/purchases/price-comparison`

**Note:** All reports return JSON data. PDF/Excel generation handled by frontend.

#### 13. Settings (`/settings`)

**Store Settings:**
- GET/PUT `/store` - Store profile
- POST/DELETE `/store/logo` - Logo management

**User Management:**
- CRUD `/users`
- POST `/users/{uuid}/activate`
- POST `/users/{uuid}/deactivate`
- POST `/users/{uuid}/reset-password`

**Branch Management:**
- CRUD `/branches`

**Permissions:**
- GET `/permissions` - All permissions
- GET/PUT `/permissions/user/{userId}` - User permissions
- GET/PUT `/permissions/role/{role}` - Role permissions

**Other Settings:**
- GET/PUT `/payment-methods` - Configure payment options
- GET/PUT `/receipt-template` - Receipt customization
- GET `/receipt-template/preview` - Preview receipt
- GET/PUT `/tax` - Tax configuration (VAT)
- GET/PUT `/credit` - Default credit settings
- GET/PUT `/system` - System settings (timezone, currency, format)
- POST `/system/clear-cache` - Clear cache

---

## Request/Response Formats

### Standard Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        // Response data
    }
}
```

### Paginated Response
```json
{
    "success": true,
    "data": {
        "data": [ /* items */ ],
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 100,
            "last_page": 7
        }
    },
    "message": "Retrieved successfully"
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

### HTTP Status Codes
- **200** - Success
- **201** - Created
- **400** - Bad Request
- **401** - Unauthorized (missing/invalid token)
- **403** - Forbidden (insufficient permissions)
- **404** - Not Found
- **422** - Validation Error
- **500** - Server Error

---

## Common Query Parameters

### Pagination
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15)

### Search & Filter
- `q` or `search` - Search query
- `status` - Filter by status
- `is_active` - Active status (1 or 0)
- `category_id` - Filter by category
- `customer_id` - Filter by customer
- `supplier_id` - Filter by supplier

### Date Filtering
- `date` - Single date (YYYY-MM-DD)
- `start_date` / `end_date` - Date range
- `date_from` / `date_to` - Alternative date range

### Sorting
- `sort_by` - Field to sort by
- `sort_order` - asc or desc

### Reports
- `days` - Number of days (1-365)
- `limit` - Number of items (1-50)
- `group_by` - day, week, month
- `export` - pdf or excel

---

## Business Logic & Workflows

### Point of Sale Workflow
1. Search/scan product → `GET /products/search` or `GET /products/barcode/{code}`
2. Build cart (frontend state)
3. Create sale → `POST /sales` with items array
4. Auto-deduct stock
5. Generate receipt → `GET /sales/{uuid}/receipt/pdf`
6. Optional: Send receipt → `POST /sales/{uuid}/receipt/send`

### Credit Sales & Payment
1. Customer credit sale → `POST /sales` with `customer_id`
2. Creates credit transaction (charge)
3. Record payment → `POST /customers/{uuid}/payments`
4. Payment allocation: **FIFO** (oldest invoices first)
5. Specific allocation: Pass `invoice_ids` array
6. Update customer balance automatically

### Credit Aging Buckets
- **Current**: 0-30 days
- **31-60 days**: Slightly overdue
- **61-90 days**: Overdue
- **Over 90 days**: Severely overdue

### Purchase Order Workflow
1. Create PO → `POST /purchase-orders` (status: draft)
2. Can edit/delete while draft
3. Submit → `POST /purchase-orders/{uuid}/submit` (status: submitted)
4. Receive → `POST /purchase-orders/{uuid}/receive` (status: received)
   - Automatically updates product stock
5. Can cancel at any stage (except received)

### Delivery Workflow
1. Create delivery → `POST /deliveries`
2. Assign driver → `POST /deliveries/{uuid}/assign-driver`
3. Update to in_transit → `PUT /deliveries/{uuid}/status`
4. Upload proof → `POST /deliveries/{uuid}/proof` (photo/signature)
5. Mark delivered → `PUT /deliveries/{uuid}/status`

---

## Security & Permissions

### Authentication Flow
1. User login → `POST /auth/login` with email/password
2. Receive Bearer token in response
3. Include in all requests: `Authorization: Bearer {token}`
4. Token stored in `personal_access_tokens` table (Sanctum)
5. Logout invalidates token → `POST /auth/logout`

### Permission System
Role-based access control with granular permissions:

**Permission Categories:**
- Products: `view_products`, `create_products`, `edit_products`, `delete_products`
- Sales: `view_sales`, `create_sales`, `void_sales`, `refund_sales`
- Customers: `view_customers`, `create_customers`, `edit_customers`, `manage_credit`
- Inventory: `view_inventory`, `adjust_stock`, `view_stock_history`
- Reports: `view_reports`, `export_reports`
- Settings: `manage_settings`, `manage_users`, `manage_permissions`

**Common Roles:**
- **admin** - Full system access
- **manager** - Sales, inventory, reports, user management
- **cashier** - POS operations only
- **inventory_clerk** - Stock management
- **accountant** - Financial reports, credit management

### Middleware Protection
```php
// All API routes protected by default
Route::middleware(['auth:sanctum', 'store.access'])->group(function () {
    // Additional permission checks via CheckPermission middleware
    Route::middleware(['permission:view_products'])->get('/products', ...);
});
```

---

## Development Guidelines

### Code Organization
```
app/
├── Http/
│   ├── Controllers/     # API controllers
│   ├── Middleware/      # Custom middleware
│   ├── Requests/        # Form request validation
│   └── Resources/       # API resource transformers
├── Models/              # Eloquent models
├── Services/            # Business logic services
└── Repositories/        # Data access layer (if used)

routes/
├── api.php             # API routes (v1)
└── web.php             # Web routes (minimal)

database/
├── migrations/         # Database migrations
├── seeders/           # Database seeders
└── factories/         # Model factories
```

### Naming Conventions
- **Routes**: Kebab-case (`purchase-orders`, `credit-aging`)
- **Models**: PascalCase singular (`Product`, `PurchaseOrder`)
- **Controllers**: PascalCase + Controller (`ProductController`)
- **Database**: Snake_case (`purchase_orders`, `credit_transactions`)
- **UUIDs**: Use UUID as primary identifier for all main entities
- **Timestamps**: `created_at`, `updated_at` (Laravel default)

### Monetary Values
**Always use centavos (integers) in database and calculations:**

```php
// CORRECT
$product->retail_price = 35000; // ₱350.00

// WRONG
$product->retail_price = 350.00; // Will cause precision issues

// Display conversion (in Resource or Accessor)
public function getRetailPriceInPesosAttribute()
{
    return $this->retail_price / 100;
}
```

### Query Optimization
- Always use eager loading to prevent N+1 queries
- Index foreign keys and frequently queried columns
- Use `select()` to limit columns when needed
- Implement database-level filtering where possible

```php
// Good - Eager loading
$sales = Sale::with(['items.product', 'customer', 'cashier'])->get();

// Bad - N+1 queries
$sales = Sale::all();
foreach ($sales as $sale) {
    $sale->items; // Separate query for each sale
}
```

---

## Testing the API

### Postman Collection
A complete Postman collection is available with 150+ endpoints:

**Files:**
- `HardwarePOS_API_Collection.postman_collection.json` - Main collection
- `HardwarePOS_Environment.postman_environment.json` - Environment variables
- `POSTMAN_COLLECTION_README.md` - Complete documentation

**Quick Start:**
1. Import both JSON files into Postman
2. Select "HardwarePOS - Local Development" environment
3. Run Login request (token auto-saves)
4. All endpoints ready to use

### Manual Testing with cURL
```bash
# Login
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Use token in requests
curl -X GET http://localhost/api/v1/products \
  -H "Authorization: Bearer {your-token}" \
  -H "Accept: application/json"
```

---

## Common Development Tasks

### Adding a New Endpoint
1. Create/update controller method
2. Add route in `routes/api.php`
3. Create form request for validation (if POST/PUT)
4. Add permission check if needed
5. Create API resource for response transformation
6. Update Postman collection
7. Add tests

### Adding a New Model
1. Create migration: `php artisan make:migration create_table_name`
2. Create model: `php artisan make:model ModelName`
3. Define relationships in model
4. Add to multi-tenancy scope if store-specific
5. Create factory and seeder
6. Run migration: `php artisan migrate`

### Debugging
```bash
# View logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Run tinker for testing
php artisan tinker
```

---

## Environment Configuration

### Required .env Variables
```env
APP_NAME=HardwarePOS
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Manila
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hardware_pos
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

# Optional: Email configuration for receipts/reminders
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525

# Optional: SMS configuration for reminders
SMS_PROVIDER=semaphore
SMS_API_KEY=your-api-key
```

---

## Deployment Considerations

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper database credentials
- [ ] Set up SSL certificate
- [ ] Configure CORS properly
- [ ] Set up backup strategy
- [ ] Configure queue workers for jobs
- [ ] Set up scheduled tasks (reminders, reports)
- [ ] Enable error logging and monitoring
- [ ] Optimize autoloader: `composer install --optimize-autoloader --no-dev`
- [ ] Cache routes and config: `php artisan optimize`

### Performance Optimization
```bash
# Production optimization
php artisan config:cache
php artisan route:cache
composer dump-autoload --optimize
```

---

## Key Features Summary

### Core Functionality
✅ Complete POS system with sales, refunds, and voids
✅ Inventory management with stock tracking
✅ **Accounts Receivable (AR)** - Customer credit with aging & FIFO allocation
✅ **Accounts Payable (AP)** - Supplier payables with aging & FIFO allocation
✅ Multi-payment method support (Cash, GCash, Maya, Bank Transfer, Check)
✅ Purchase order management with automatic AP invoice creation
✅ Delivery tracking with proof of delivery
✅ Comprehensive reporting and analytics (JSON data)
✅ Multi-tenant architecture (store-level isolation)
✅ Role-based permissions
✅ Activity logging and audit trail
✅ Multi-branch support
✅ **Pure JSON API** - No server-side rendering, all UI in frontend

### Business Features
✅ AR/AP with FIFO payment allocation
✅ 4-bucket aging analysis (current, 31-60, 61-90, over-90)
✅ Customer & supplier statements (JSON)
✅ Low stock alerts and reorder points
✅ Sales trends and analytics
✅ Top products and customers
✅ Profit analysis
✅ Dead stock identification
✅ Supplier price comparison
✅ Hold/resume POS transactions

---

## Integration Points

### Potential Integrations
- **Payment Gateways**: GCash, Maya, PayMongo for online payments
- **SMS Gateway**: Semaphore, Twilio for notifications
- **Email Service**: SendGrid, Mailgun for transactional emails
- **Cloud Storage**: AWS S3, Cloudinary for images/documents
- **Barcode Scanner**: Hardware integration via frontend
- **Receipt Printer**: ESC/POS printer integration
- **Accounting Software**: Export for QuickBooks, Xero

---

## Contact & Support

For questions or issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Review Postman collection documentation
3. Check API documentation: `/api/documentation` (if Scramble configured)

---

## License

This project is proprietary software. All rights reserved.

---

**Last Updated**: February 2024
**Laravel Version**: 11.x
**PHP Version**: 8.2+
**API Version**: v1
