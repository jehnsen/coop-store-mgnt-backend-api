# CoopStore Management - Backend API

## Project Overview

**CoopStore Management** is a comprehensive cloud-based management system for **Multi-Purpose Cooperatives (MPC)** in the Philippines. This Laravel-based backend API covers two major functional domains:

1. **Retail / POS** — Sales, inventory, customer credit, purchase orders, deliveries, and business analytics (original HardwarePOS core, repurposed for cooperative stores)
2. **MPC / Cooperative Finance** — Share capital, lending, savings, time deposits, patronage refunds, membership lifecycle, CDA compliance reporting, and a Mutual Aid Fund (MAF)

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
- Each user belongs to a specific store (cooperative)
- All data is automatically filtered by `store_id` via the `store.access` middleware
- Complete data isolation between stores/cooperatives
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

> **API Note**: Request bodies accept values in **pesos** (e.g. `"amount": 500.00`). Services convert to centavos before persisting.

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

### Key Packages
- **Laravel Sanctum** - API authentication with tokens
- **Scramble** - API documentation generation (`/api/documentation`)
- **Spatie Activity Log** - Audit trail and activity logging

### Architecture Note
This is a **pure JSON API backend**. All PDF generation, Excel exports, and UI rendering are handled by the frontend application. The backend returns structured JSON data only.

---

## Database Structure

### Core Models & Relationships

#### Store & Users
- **Store** - Multi-tenant cooperative entity
  - Has many: Users, Products, Sales, Customers, etc.
- **User** - System users (admin, manager, cashier, teller, etc.)
  - Belongs to: Store, Branch
  - Has many: Sales (as cashier), ActivityLogs
  - Uses: Laravel Sanctum for API tokens

#### Products & Inventory
- **Product** - Product catalog
  - Belongs to: Store, Category, UnitOfMeasure
  - Has many: SaleItems, PurchaseOrderItems, StockAdjustments
  - Fields: uuid, name, sku, barcode, cost_price, retail_price, current_stock, reorder_point
- **Category** - Product categories
- **UnitOfMeasure** - Units (pcs, box, kg, etc.)

#### Sales & POS
- **Sale** - Sales transactions
  - Belongs to: Store, Customer (optional), Cashier (User), Branch
  - Has many: SaleItems, Payments
  - Fields: uuid, sale_number, subtotal, tax, total, status, payment_method
  - Statuses: completed, voided, refunded
- **SaleItem** - Line items in a sale
- **HeldTransaction** - Paused POS transactions (resumable)

#### Customers & Credit (AR)
- **Customer** - Customer / member records
  - Belongs to: Store
  - Has many: Sales, CreditTransactions, Deliveries, MemberShareAccount, MemberSavingsAccount, Loans, etc.
  - Fields: uuid, name, type, credit_limit, credit_terms_days, current_balance, member_status
  - Types: walk_in, regular, contractor, government
  - Member statuses: non_member, pending, active, inactive, expelled, resigned
- **CreditTransaction** - AR credit ledger entries (charge, payment, adjustment)
  - Payment allocation: FIFO (First In, First Out)

#### Supply Chain & Accounts Payable
- **Supplier** - Supplier management
- **PurchaseOrder** - Statuses: draft, submitted, received, cancelled
- **PayableTransaction** - AP ledger (invoice, payment, adjustment); FIFO allocation
- **Delivery** - Delivery tracking with proof of delivery (photo/signature)

#### Settings & Configuration
- **Branch** - Store branches for multi-location support
- **Permission** - Role-based access control
- **StoreSettings** - Store profile, tax settings, receipt templates, etc.

---

### MPC / Cooperative Finance Models

#### Share Capital
- **MemberShareAccount** - Member share capital subscription
  - Belongs to: Customer (member)
  - Has many: ShareCapitalPayments, ShareCertificates
  - Fields: uuid, share_type (common|preferred), subscribed_shares, par_value_per_share, paid_up_amount, status
- **ShareCapitalPayment** - Individual share payments
- **ShareCertificate** - Issued share certificates

#### Loans & Lending
- **LoanProduct** - Loan product configuration
  - Fields: code, name, loan_type, interest_rate, max_term_months, min/max_amount, processing_fee_rate, service_fee, requires_collateral
- **Loan** - Individual loan records
  - Belongs to: Customer, LoanProduct
  - Has many: LoanAmortizationSchedules, LoanPayments, LoanPenalties
  - Statuses: pending, approved, disbursed, active, paid, rejected, written_off
- **LoanAmortizationSchedule** - Per-period amortization rows
- **LoanPayment** - Loan payment records
- **LoanPenalty** - Penalty charges for overdue amortizations

#### Savings
- **MemberSavingsAccount** - Member savings accounts
  - Belongs to: Customer
  - Has many: SavingsTransactions
  - Types: voluntary, compulsory
  - Statuses: active, closed
- **SavingsTransaction** - Deposits, withdrawals, interest credits, reversals

#### Time Deposits
- **TimeDeposit** - Time deposit placements
  - Belongs to: Customer
  - Has many: TimeDepositTransactions
  - Statuses: active, matured, pre_terminated, rolled_over
  - Fields: principal_amount, interest_rate, term_months, placement_date, maturity_date, interest_method, payment_frequency
- **TimeDepositTransaction** - Accruals, payouts, rollovers, pre-terminations

#### Patronage Refunds
- **PatronageRefundBatch** - Annual/periodic refund computation batch
  - Statuses: draft, computed, approved, disbursed
  - Fields: period_label, period_from/to, computation_method, pr_rate, pr_fund
- **PatronageRefundAllocation** - Per-member allocation within a batch
  - Statuses: pending, paid, forfeited

#### Membership
- **MembershipApplication** - Member enrollment applications
  - Statuses: pending, approved, rejected
- **MembershipFee** - Admission, annual, and reinstatement fees

#### CDA Compliance
- **CdaAnnualReport** - CDA annual report per year
  - Statuses: draft, finalized, submitted
- **AgaRecord** - Annual General Assembly meeting records
  - Types: annual, special; Statuses: draft, finalized
- **CoopOfficer** - Board of Directors / officers registry

#### Mutual Aid Fund (MAF)
- **MafProgram** - Benefit program configuration (death, disability, hospitalization, accident)
- **MafContribution** - Member contribution payments
- **MafClaim** - Claims filed against a benefit program
  - Statuses: pending, under_review, approved, rejected, paid
- **MafClaimPayment** - Disbursement records
- **MafBeneficiary** - Named beneficiaries per member

---

## API Endpoints Overview

### Complete API Structure (250+ endpoints)

#### 1. Authentication (`/auth`)
- POST `/login` - Authenticate user (returns Bearer token)
- POST `/logout` - Invalidate current token
- GET `/me` - Get authenticated user
- PUT `/profile` - Update user profile
- POST `/change-password` - Change password
- POST `/forgot-password` - Request password reset
- POST `/reset-password` - Complete password reset

#### 2. Products (`/products`)
- Standard CRUD: GET, POST, PUT, DELETE
- GET `/search` - Fast POS search (name/SKU/barcode)
- GET `/barcode/{barcode}` - Barcode lookup
- GET `/low-stock` - Products below reorder point
- POST `/{uuid}/adjust-stock` - Stock adjustment
- GET `/{uuid}/stock-history` - Stock movement history
- POST `/bulk-update` - Bulk update multiple products

#### 3. Categories (`/categories`)
- Standard CRUD + POST `/reorder`

#### 4. Units (`/units`)
- Standard CRUD for units of measure

#### 5. Sales - POS (`/sales`)
- Standard CRUD with status filters
- POST `/{uuid}/void` - Void sale
- POST `/{uuid}/refund` - Full or partial refund
- GET `/{uuid}/receipt` - Receipt data (JSON)
- POST `/hold` - Hold/pause transaction
- GET `/held/list` - List held transactions
- GET `/held/{id}/resume` - Resume held transaction
- DELETE `/held/{id}` - Discard held transaction
- GET `/next-number/preview` - Preview next sale number

#### 6. Customers (`/customers`)
- Standard CRUD with advanced search
- GET `/{uuid}/transactions` - Credit transaction history
- GET `/{uuid}/credit-ledger` - Credit ledger
- POST `/{uuid}/payments` - Record payment (FIFO allocation)
- PUT `/{uuid}/credit-limit` - Adjust credit limit
- GET `/{uuid}/statement` - Customer statement (JSON)
- GET `/credit/overview` - AR statistics
- GET `/credit/aging` - 4-bucket aging analysis
- GET `/credit/overdue` - Overdue accounts
- GET `/export` - Export customer list
- POST `/{uuid}/send-reminder` - Send payment reminder

#### 7. Dashboard (`/dashboard`)
- GET `/summary` - Today's summary stats
- GET `/sales-trend?days=30` - Sales trend
- GET `/top-products?limit=10` - Top selling products
- GET `/sales-by-category` - Category breakdown
- GET `/credit-aging` - Credit aging summary
- GET `/recent-transactions` - Recent sales
- GET `/stock-alerts` - Low stock warnings
- GET `/upcoming-deliveries` - Delivery schedule
- GET `/top-customers` - Top customers by sales
- GET `/comprehensive` - Full dashboard in one call

#### 8. Suppliers (`/suppliers`)
- Standard CRUD
- GET `/{uuid}/products` - Supplier's products
- POST `/{uuid}/products` - Link product to supplier
- DELETE `/{uuid}/products/{productUuid}` - Unlink product
- GET `/{uuid}/price-history` - Price history
- GET/POST `/{uuid}/payables` - AP payable transactions
- GET `/{uuid}/ledger` - AP ledger (alias)
- POST `/{uuid}/payments` - Make AP payment (FIFO)
- GET `/{uuid}/statement` - Supplier AP statement

#### 9. Accounts Payable (`/ap`)
- GET `/overview` - AP statistics
- GET `/aging` - 4-bucket aging analysis
- GET `/overdue` - Overdue suppliers
- GET `/payment-schedule` - Upcoming payments
- GET `/disbursement-report` - Payments made report

#### 10. Purchase Orders (`/purchase-orders`)
- Standard CRUD
- POST `/{uuid}/submit` - Submit PO
- POST `/{uuid}/receive` - Receive PO (updates stock + creates AP invoice)
- POST `/{uuid}/cancel` - Cancel PO
- GET `/{uuid}/pdf` - PO PDF data (JSON)

#### 11. Deliveries (`/deliveries`)
- GET `/today-schedule` - Today's deliveries
- Standard CRUD
- PUT `/{uuid}/status` - Update status
- POST `/{uuid}/proof` - Upload proof of delivery
- GET `/{uuid}/proof/download` - Download proof
- GET `/{uuid}/receipt` - Delivery receipt (JSON)
- GET `/{uuid}/receipt/pdf` - Delivery receipt PDF data
- POST `/{uuid}/assign-driver` - Assign driver

#### 12. Reports (`/reports`)
- **Sales**: `/sales/daily`, `/sales/summary`, `/sales/by-category`, `/sales/by-customer`, `/sales/by-payment-method`, `/sales/by-cashier`
- **Inventory**: `/inventory/valuation`, `/inventory/movement`, `/inventory/low-stock`, `/inventory/dead-stock`, `/inventory/profitability`
- **Credit**: `/credit/aging`, `/credit/collection`
- **Purchases**: `/purchases/by-supplier`, `/purchases/price-comparison`

#### 13. Settings (`/settings`)
- Store profile, logo, users, branches, permissions, payment methods, receipt template, tax, credit defaults, system settings, cache clear

---

### MPC Cooperative Finance Endpoints

#### 14. Share Capital (`/share-capital`)
- GET `/overview` - Share capital module statistics
- POST `/compute-isc` - Compute Interest on Share Capital (ISC) for a year
- Standard CRUD for share accounts
- POST `/{uuid}/payments` - Record share payment
- GET `/{uuid}/payments` - List payments
- DELETE `/{uuid}/payments/{payUuid}` - Reverse payment
- POST `/{uuid}/certificates` - Issue share certificate
- GET `/{uuid}/certificates` - List certificates
- DELETE `/{uuid}/certificates/{certUuid}` - Cancel certificate (with reason)
- GET `/{uuid}/statement` - Account statement (JSON)
- POST `/{uuid}/withdraw` - Process share capital withdrawal

#### 15. Loan Products (`/loan-products`)
- Standard CRUD for loan product configuration
- Fields: code, name, loan_type, interest_rate, max_term_months, min/max_amount, processing_fee_rate, service_fee, requires_collateral

#### 16. Loans (`/loans`)
- GET `/overview` - Loans module statistics
- GET `/delinquent` - Delinquent loans list
- GET `/aging` - Loan aging analysis
- POST `/amortization/preview` - Preview schedule before applying
- Standard CRUD for loan applications
- POST `/{uuid}/approve` - Approve application
- POST `/{uuid}/reject` - Reject application (with reason)
- POST `/{uuid}/disburse` - Disburse approved loan
- POST `/{uuid}/payments` - Record loan payment
- GET `/{uuid}/payments` - List payments
- DELETE `/{uuid}/payments/{payUuid}` - Reverse payment
- GET `/{uuid}/schedule` - Full amortization schedule with status
- GET `/{uuid}/statement` - Loan statement (JSON)
- POST `/{uuid}/penalties/compute` - Compute overdue penalties
- POST `/{uuid}/penalties/{penUuid}/waive` - Waive a penalty

#### 17. Savings (`/savings`)
- GET `/overview` - Savings module statistics
- POST `/batch-credit-interest` - Batch credit interest to all active accounts
- Standard CRUD for savings accounts
- POST `/{uuid}/deposit` - Record deposit
- POST `/{uuid}/withdraw` - Record withdrawal
- GET `/{uuid}/transactions` - List transactions
- DELETE `/{uuid}/transactions/{txUuid}` - Reverse transaction
- GET `/{uuid}/statement` - Account statement (JSON)
- POST `/{uuid}/close` - Close account and disburse balance

#### 18. Time Deposits (`/time-deposits`)
- GET `/overview` - Time deposit module statistics
- POST `/interest-preview` - Preview interest before placement
- Standard CRUD for time deposit placements
- POST `/{uuid}/accrue` - Accrue interest for a period
- POST `/{uuid}/mature` - Process maturity payout
- POST `/{uuid}/pre-terminate` - Pre-terminate (penalty applies)
- POST `/{uuid}/rollover` - Rollover into new placement
- GET `/{uuid}/transactions` - List transactions
- GET `/{uuid}/statement` - Statement (JSON)

#### 19. Patronage Refunds (`/patronage-refunds`)
- GET `/overview` - Module statistics
- Standard CRUD for refund batches
- GET `/{uuid}/summary` - Batch computation summary
- POST `/{uuid}/compute` - Trigger computation for all eligible members
- POST `/{uuid}/approve` - Approve the batch
- GET `/{uuid}/allocations` - List per-member allocations
- POST `/{uuid}/allocations/{allocUuid}/pay` - Mark allocation as paid
- POST `/{uuid}/allocations/{allocUuid}/forfeit` - Forfeit unclaimed allocation

#### 20. Memberships (`/memberships`)
- GET `/overview` - Module statistics
- GET/POST `/applications` - List / submit membership applications
- GET `/{uuid}` - Get application
- POST `/applications/{uuid}/approve` - Approve (records admission fee)
- POST `/applications/{uuid}/reject` - Reject (with reason)
- GET `/members` - List members with status filter
- POST `/members/{uuid}/deactivate` - Deactivate member
- POST `/members/{uuid}/reinstate` - Reinstate member (records reinstatement fee)
- POST `/members/{uuid}/expel` - Expel member (with reason)
- POST `/members/{uuid}/resign` - Process resignation
- GET/POST `/fees` - List / record membership fees (admission, annual, reinstatement)
- DELETE `/fees/{uuid}` - Reverse fee

#### 21. CDA Compliance (`/cda`)
- GET `/overview` - Compliance module overview
- GET/POST `/reports` - List / compile annual reports from system data
- GET/PUT `/{uuid}` - Get / update report details
- POST `/{uuid}/finalize` - Lock report from further edits
- POST `/{uuid}/mark-submitted` - Mark as submitted to CDA
- GET `/{uuid}/statistical-data` - Raw data used in the report
- GET/POST `/aga` - List / create AGA meeting records
- GET/PUT/DELETE `/aga/{uuid}` - Get / update / delete AGA record
- POST `/aga/{uuid}/finalize` - Finalize AGA record
- GET/POST `/officers` - List / add board officers
- GET/PUT/DELETE `/officers/{uuid}` - Get / update / delete officer

#### 22. MAF – Mutual Aid Fund (`/maf`)
- GET `/overview` - Fund balance and statistics
- GET `/claims-report` - Claims report for a date range
- Standard CRUD for benefit programs (`/maf`, `/maf/{uuid}`)
- GET/POST `/contributions` - List / record contributions
- POST `/contributions/{uuid}/reverse` - Reverse a contribution
- GET/POST `/claims` - List / submit claims
- GET `/claims/{uuid}` - Get claim details
- POST `/claims/{uuid}/review` - Move to under-review
- POST `/claims/{uuid}/approve` - Approve with approved amount
- POST `/claims/{uuid}/reject` - Reject with reason
- POST `/claims/{uuid}/pay` - Disburse claim payment

**Member-scoped MAF routes** (`/customers/{uuid}/...`):
- GET `/maf-contributions` - Member's contributions
- GET `/maf-claims` - Member's claims
- GET/POST `/maf-beneficiaries` - List / add beneficiaries
- PUT `/maf-beneficiaries/{bUuid}` - Update beneficiary
- POST `/maf-beneficiaries/{bUuid}/deactivate` - Deactivate beneficiary

---

## Request/Response Formats

### Standard Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {}
}
```

### Paginated Response
```json
{
    "success": true,
    "data": {
        "data": [],
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
- `customer_uuid` - Filter by member/customer
- `category_id`, `supplier_id` - Domain-specific filters

### Date Filtering
- `date` - Single date (YYYY-MM-DD)
- `start_date` / `end_date` - Date range
- `date_from` / `date_to` - Alternative date range

### Sorting
- `sort_by` - Field to sort by
- `sort_order` - asc or desc

---

## Business Logic & Workflows

### Point of Sale Workflow
1. Search/scan product → `GET /products/search` or `GET /products/barcode/{code}`
2. Build cart (frontend state)
3. Create sale → `POST /sales` with items array
4. Auto-deduct stock, create credit transaction if member purchase on credit
5. Generate receipt → `GET /sales/{uuid}/receipt`

### Loan Lifecycle
1. Submit application → `POST /loans` (status: pending)
2. Approve → `POST /loans/{uuid}/approve` (status: approved)
3. Disburse → `POST /loans/{uuid}/disburse` (status: disbursed → active)
4. Record monthly payments → `POST /loans/{uuid}/payments`
5. Compute penalties if overdue → `POST /loans/{uuid}/penalties/compute`
6. Waive penalties if approved → `POST /loans/{uuid}/penalties/{penUuid}/waive`
7. Loan paid off → status transitions to **paid**

### Membership Lifecycle
1. Submit application → `POST /memberships/applications`
2. Approve (records admission fee) → `POST /memberships/applications/{uuid}/approve`
3. Member becomes **active** on the Customer record (`member_status = active`)
4. Open share capital account, savings account, etc.
5. Status transitions: active → inactive → reinstate → expelled / resigned

### Patronage Refund Workflow
1. Create batch for a period → `POST /patronage-refunds`
2. Trigger computation → `POST /{uuid}/compute` (calculates per-member allocation)
3. Review summary → `GET /{uuid}/summary`
4. Approve batch → `POST /{uuid}/approve`
5. Disburse per member → `POST /{uuid}/allocations/{allocUuid}/pay`
6. Forfeit unclaimed → `POST /{uuid}/allocations/{allocUuid}/forfeit`

### MAF Claim Lifecycle
1. Submit claim → `POST /maf/claims`
2. Set to under-review → `POST /claims/{uuid}/review`
3. Approve with amount → `POST /claims/{uuid}/approve`
4. Disburse → `POST /claims/{uuid}/pay`

### Credit Sales & Payment (AR)
1. Member credit sale → `POST /sales` with `customer_uuid`
2. Creates credit transaction (charge)
3. Record payment → `POST /customers/{uuid}/payments`
4. Payment allocation: **FIFO** (oldest charges first)

### Purchase Order Workflow (AP)
1. Create PO → `POST /purchase-orders` (status: draft)
2. Submit → `POST /purchase-orders/{uuid}/submit`
3. Receive → `POST /purchase-orders/{uuid}/receive`
   - Automatically updates product stock
   - Creates AP invoice in `PayableTransaction`
4. Pay supplier → `POST /suppliers/{uuid}/payments` (FIFO allocation)

### Credit / AP Aging Buckets
- **Current**: 0–30 days
- **31–60 days**: Slightly overdue
- **61–90 days**: Overdue
- **Over 90 days**: Severely overdue

---

## Security & Permissions

### Authentication Flow
1. User login → `POST /auth/login` with email/password
2. Receive Bearer token in response
3. Include in all requests: `Authorization: Bearer {token}`
4. Logout invalidates token → `POST /auth/logout`

### Permission System
Role-based access control with granular permissions:

**Permission Categories:**
- Products: `view_products`, `create_products`, `edit_products`, `delete_products`
- Sales: `view_sales`, `create_sales`, `void_sales`, `refund_sales`
- Customers/Members: `view_customers`, `create_customers`, `edit_customers`, `manage_credit`
- Inventory: `view_inventory`, `adjust_stock`, `view_stock_history`
- Reports: `view_reports`, `export_reports`
- Settings: `manage_settings`, `manage_users`, `manage_permissions`

**Common Roles:**
- **admin** - Full system access
- **manager** - Sales, inventory, reports, user management
- **cashier** / **teller** - POS and savings/loan payment operations
- **loan_officer** - Loan processing and management
- **accountant** - Financial reports, credit management, AP/AR

---

## Development Guidelines

### Code Organization
```
app/
├── Http/
│   ├── Controllers/Api/   # API controllers (one per domain)
│   ├── Middleware/        # Custom middleware
│   ├── Requests/          # Form request validation (grouped by domain)
│   └── Resources/         # API resource transformers
├── Models/                # Eloquent models
└── Services/              # Business logic services (one per domain)

routes/
└── api.php                # All API routes (v1)

database/
└── migrations/            # Timestamped migrations
```

### Naming Conventions
- **Routes**: Kebab-case (`share-capital`, `loan-products`, `time-deposits`)
- **Models**: PascalCase singular (`LoanProduct`, `MemberSavingsAccount`)
- **Controllers**: PascalCase + Controller (`LoanController`, `MafController`)
- **Database tables**: Snake_case plural (`member_share_accounts`, `loan_amortization_schedules`)
- **Primary identifiers**: UUID (`uuid` column) on all main entities
- **Timestamps**: `created_at`, `updated_at` (Laravel default)

### Monetary Values
**Always store and calculate in centavos (integers):**

```php
// CORRECT — store as centavos
$loan->principal_amount = 1000000; // ₱10,000.00

// WRONG — floating point
$loan->principal_amount = 10000.00;

// API inputs arrive in pesos; convert in Service layer
$amountCentavos = (int) round($request->amount * 100);

// Display conversion (in Resource)
'amount' => $this->amount / 100,
```

### Query Optimization
- Always use eager loading to prevent N+1 queries
- Index foreign keys and frequently queried columns
- Use `select()` to limit columns when appropriate

```php
// Good
$loans = Loan::with(['customer', 'loanProduct', 'amortizationSchedules'])->get();

// Bad — N+1
$loans = Loan::all();
foreach ($loans as $loan) { $loan->customer; }
```

---

## Testing the API

### Postman Collection
A complete Postman collection is available in the `docs/` directory:

**Files:**
- `docs/API_Collection.postman_collection.json` - Full collection (22 sections, 250+ requests)
- `docs/API_Environment.postman_environment.json` - Environment variables

**Quick Start:**
1. Import both JSON files into Postman
2. Select "HardwarePOS - Local Development" environment (base_url defaults to `http://localhost`)
3. Run **Login** request — token auto-saves to collection variable
4. All endpoints ready to use

### Manual Testing with cURL
```bash
# Login
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Authenticated request
curl -X GET http://localhost/api/v1/share-capital \
  -H "Authorization: Bearer {your-token}" \
  -H "Accept: application/json"
```

---

## Common Development Tasks

### Adding a New Endpoint
1. Create/update controller method in `app/Http/Controllers/Api/`
2. Add route in `routes/api.php`
3. Create form request in `app/Http/Requests/{Domain}/` for POST/PUT
4. Add permission check if needed
5. Create/update API resource in `app/Http/Resources/`
6. Add business logic to the relevant Service in `app/Services/`
7. Update `docs/API_Collection.postman_collection.json`

### Adding a New Model
1. `php artisan make:migration create_table_name_table`
2. `php artisan make:model ModelName`
3. Define fillable, casts, and relationships
4. Add `store_id` scope if store-specific (multi-tenancy)
5. Run `php artisan migrate`

### Debugging
```bash
# View logs
tail -f storage/logs/laravel.log

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear

# Interactive tinker
php artisan tinker
```

---

## Environment Configuration

### Required .env Variables
```env
APP_NAME="CoopStore Management"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Manila
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coop_store_mgnt
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

# Optional: Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525

# Optional: SMS (member reminders)
SMS_PROVIDER=semaphore
SMS_API_KEY=your-api-key
```

---

## Deployment Considerations

### Production Checklist
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure database credentials
- [ ] Set up SSL certificate
- [ ] Configure CORS
- [ ] Set up automated database backups
- [ ] Configure queue workers (`php artisan queue:work`)
- [ ] Set up scheduled tasks for batch jobs (interest crediting, penalty computation)
- [ ] Enable error logging/monitoring
- [ ] `composer install --optimize-autoloader --no-dev`
- [ ] `php artisan optimize`

### Performance Optimization
```bash
php artisan config:cache
php artisan route:cache
composer dump-autoload --optimize
```

---

## Key Features Summary

### Retail / POS Core
- Complete POS system (sales, refunds, voids, held transactions)
- Inventory management with stock tracking and reorder alerts
- Accounts Receivable (AR) — customer credit with FIFO allocation and aging
- Accounts Payable (AP) — supplier payables with FIFO allocation and aging
- Multi-payment method support (Cash, GCash, Maya, Bank Transfer, Check)
- Purchase order management with automatic AP invoice creation
- Delivery tracking with proof of delivery
- Comprehensive reporting and analytics (pure JSON)
- Multi-tenant architecture (cooperative/store-level isolation)
- Role-based permissions and activity audit trail
- Multi-branch support

### MPC / Cooperative Finance
- **Share Capital** — subscriptions, payments, ISC computation, share certificates
- **Loans** — full lifecycle: application → approval → disbursement → repayment → penalties
- **Savings** — voluntary/compulsory accounts, batch interest crediting
- **Time Deposits** — placement, accrual, maturity, pre-termination, rollover
- **Patronage Refunds** — computation, approval, per-member disbursement/forfeiture
- **Memberships** — application workflow, status transitions, fee tracking
- **CDA Compliance** — annual reports, AGA records, officers registry
- **MAF** — benefit programs, contributions, claim lifecycle (review → approve → pay)

---

**Last Updated**: February 2026
**Laravel Version**: 11.x
**PHP Version**: 8.2+
**API Version**: v1
