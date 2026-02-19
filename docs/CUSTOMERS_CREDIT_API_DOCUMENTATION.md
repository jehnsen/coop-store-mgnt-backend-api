# Customers and Credit Management API Documentation

## Overview

This document provides comprehensive documentation for the Customers and Credit Management API implementation in the HardwarePOS system. The API manages customer accounts, credit limits, payment tracking, and aging reports.

## Files Created

### 1. Form Requests (`app/Http/Requests/Customer/`)

#### StoreCustomerRequest.php
Validates customer creation with the following fields:
- **name**: required, string, max:255
- **type**: required, in:walk_in,regular,contractor,government
- **email**: nullable, email, max:255
- **phone**: required, string, max:20
- **alternate_phone**: nullable, string, max:20
- **address**: nullable, string
- **city**: nullable, string, max:100
- **province**: nullable, string, max:100
- **postal_code**: nullable, string, max:10
- **tin**: nullable, string, max:20
- **business_name**: nullable, string, max:255
- **credit_limit**: nullable, integer, min:0 (in centavos)
- **credit_terms_days**: nullable, integer, min:1, max:365
- **is_active**: boolean (default: true)
- **notes**: nullable, string

#### UpdateCustomerRequest.php
Same as StoreCustomerRequest but all fields use 'sometimes' rule for partial updates.

#### RecordPaymentRequest.php
Validates payment recording:
- **amount**: required, integer, min:1 (in centavos)
- **payment_method**: required, in:cash,gcash,maya,bank_transfer,check
- **reference_number**: nullable, string, max:100
- **payment_date**: nullable, date (defaults to today)
- **invoice_ids**: nullable, array (specific sales to apply payment to)
- **invoice_ids.***: exists:sales,uuid
- **notes**: nullable, string, max:500

**Special Validation**: Amount cannot exceed customer's total outstanding balance.

#### AdjustCreditLimitRequest.php
Validates credit limit adjustments:
- **credit_limit**: required, integer, min:0 (in centavos)
- **reason**: required, string, max:500

### 2. API Resources (`app/Http/Resources/`)

#### CustomerResource.php
Transforms customer data including:
- Basic information (uuid, name, type, contact details, address)
- Credit summary (credit_limit, total_outstanding, available_credit, total_purchases)
- Computed fields (customer_tier, last_purchase_date)
- Relationships (sales count when loaded)

**Customer Tier Calculation**:
- Bronze: ₱0 - ₱100,000
- Silver: ₱100,001 - ₱500,000
- Gold: ₱500,001 - ₱1,000,000
- Platinum: ₱1,000,001+

#### CreditTransactionResource.php
Transforms credit transaction data including:
- Transaction details (type, amount, reference, payment method)
- Balance tracking (balance_before, balance_after)
- Status computation (outstanding, paid, overdue, partially_paid)
- Dates (transaction_date, due_date, paid_date)
- Related sale information
- Days overdue calculation

#### CreditAgingResource.php
Transforms aging report data:
- Customer basic information
- Aging buckets:
  - Current (0-30 days)
  - 31-60 days
  - 61-90 days
  - Over 90 days
- Total outstanding
- Credit utilization percentage
- Oldest invoice days

### 3. CreditService (`app/Services/CreditService.php`)

Core business logic service with the following methods:

#### chargeCredit(Customer, Sale, int $amount, int $termsDays): CreditTransaction
- Creates credit charge transaction
- Calculates due_date (today + terms_days)
- Updates customer's total_outstanding
- Logs activity
- Returns CreditTransaction with relationships

#### receivePayment(Customer, int $amount, string $method, ?string $reference, ?array $invoiceUuids, ?string $notes): array
- Creates payment transaction (negative amount)
- Allocates payment to invoices:
  - If specific invoices provided: applies to those
  - Else: FIFO allocation (oldest outstanding first)
- Updates customer's total_outstanding
- Updates related sales payment_status
- Returns transaction and allocation details
- Logs activity

#### getAgingReport(Store): array
- Queries all customers with outstanding balances
- Categorizes transactions by age from due_date:
  - Current (0-30 days)
  - 31-60 days
  - 61-90 days
  - 90+ days
- Returns customers collection with aging data and summary

#### getCustomerStatement(Customer, Carbon $from, Carbon $to): array
- Gets all credit transactions in date range
- Calculates opening balance
- Formats transactions with running balance
- Returns complete statement with summary

#### checkCreditAvailability(Customer, int $amount): array
- Calculates available_credit = credit_limit - total_outstanding
- Returns availability status and details

#### updateOutstandingBalance(Customer): void
- Recalculates total_outstanding from unpaid charges
- Updates customer record
- Private helper method

#### getOverdueAccounts(Store): Collection
- Queries customers with overdue transactions
- Returns collection with overdue details

#### adjustCreditLimit(Customer, int $newLimit, string $reason): Customer
- Updates credit limit
- Logs activity with reason
- Returns updated customer

#### markOverdueTransactions(): int
- Identifies transactions past due_date
- Returns count of overdue transactions
- Should run daily via scheduled command

### 4. CustomerController (`app/Http/Controllers/Api/CustomerController.php`)

REST API controller with the following endpoints:

#### index(Request): JsonResponse
- Paginated list of customers
- Search: name, phone, email, code
- Filters: type, is_active, has_outstanding_balance
- Sorting support
- Returns CustomerResource collection

#### store(StoreCustomerRequest): JsonResponse
- Creates new customer
- Auto-generates customer code (CUST-XXXXX)
- Initializes totals to 0
- Returns CustomerResource with 201 status

#### show(string $uuid): JsonResponse
- Gets customer by UUID
- Includes sales count
- Returns CustomerResource

#### update(UpdateCustomerRequest, string $uuid): JsonResponse
- Updates customer
- Returns CustomerResource

#### destroy(string $uuid): JsonResponse
- Soft deletes customer
- Prevents deletion if has outstanding balance
- Returns success message

#### transactions(Request, string $uuid): JsonResponse
- Gets customer's credit transactions
- Pagination support
- Filters: type, date_range
- Returns CreditTransactionResource collection

#### creditLedger(Request, string $uuid): JsonResponse
- Alias for transactions() with detailed view

#### recordPayment(RecordPaymentRequest, string $uuid): JsonResponse
- Records payment
- Returns transaction and allocation details

#### adjustCreditLimit(AdjustCreditLimitRequest, string $uuid): JsonResponse
- Adjusts credit limit
- Returns updated CustomerResource

#### statement(Request, string $uuid): JsonResponse
- Gets customer statement
- Validates start_date and end_date
- Returns formatted statement data

#### statementPdf(Request, string $uuid)
- Generates PDF statement
- Uses DomPDF
- Returns PDF download

#### sendReminder(Request, string $uuid): JsonResponse
- Sends SMS/email reminder about outstanding balance
- Validates customer has outstanding balance
- Returns success message

#### creditOverview(): JsonResponse
- Returns credit management statistics:
  - total_customers_with_credit
  - total_outstanding
  - total_available_credit
  - average_credit_utilization

#### creditAging(): JsonResponse
- Returns aging report
- Uses CreditService->getAgingReport()
- Returns CreditAgingResource collection with summary

#### overdue(): JsonResponse
- Returns overdue accounts
- Uses CreditService->getOverdueAccounts()
- Returns collection with overdue details

#### export(Request)
- Exports customers to Excel
- Supports filters
- Placeholder for Laravel Excel implementation

### 5. Routes (`routes/api.php`)

All routes are protected with `auth:sanctum` and `store.access` middleware.

**Credit Management Routes** (must come first to avoid conflicts):
```
GET  /customers/credit/overview
GET  /customers/credit/aging
GET  /customers/credit/overdue
GET  /customers/export
```

**Customer CRUD Routes**:
```
GET    /customers
POST   /customers
GET    /customers/{uuid}
PUT    /customers/{uuid}
DELETE /customers/{uuid}
```

**Credit Operations Routes**:
```
GET  /customers/{uuid}/transactions
GET  /customers/{uuid}/credit-ledger
POST /customers/{uuid}/payments
PUT  /customers/{uuid}/credit-limit
GET  /customers/{uuid}/statement
GET  /customers/{uuid}/statement/pdf
POST /customers/{uuid}/send-reminder
```

### 6. Blade Template (`resources/views/statements/customer.blade.php`)

Professional PDF statement template featuring:
- Store header with logo space
- Customer information box
- Statement period display
- Opening balance
- Transaction table with:
  - Date, Reference, Description
  - Charges, Payments, Running Balance
- Closing balance
- Transaction summary box
- Terms and conditions
- Professional footer with "Thank you" message

## Critical Implementation Details

### 1. Monetary Values (Centavos)
- All amounts stored as integers in centavos (1 peso = 100 centavos)
- Models use Attribute accessors/mutators for automatic conversion
- Resources convert to pesos for API responses
- Requests accept centavos for precise calculations

### 2. FIFO Payment Allocation
When no specific invoices are provided:
1. Query outstanding sales ordered by sale_date ASC
2. Apply payment to oldest first
3. Update each sale's amount_paid and payment_status
4. Continue until payment is fully allocated
5. Track allocation in response

### 3. Credit Limit Enforcement
Before allowing credit sale:
```php
$availability = $creditService->checkCreditAvailability($customer, $amount);
if (!$availability['available']) {
    // Reject sale or require additional authorization
}
```

### 4. Aging Calculation
Based on due_date (not transaction_date):
- Current: 0-30 days from due_date
- 31-60 days: 31-60 days past due_date
- 61-90 days: 61-90 days past due_date
- 90+ days: more than 90 days past due_date

### 5. Status Management
Transaction status computed dynamically:
- **outstanding**: unpaid, not yet due
- **overdue**: unpaid, past due_date
- **partially_paid**: some payment applied
- **paid**: fully paid
- **reversed**: transaction reversed

### 6. Running Balance
Each transaction records:
- balance_before: customer balance before transaction
- balance_after: customer balance after transaction
- Used for statement generation and audit trail

### 7. Activity Logging
All credit operations logged using spatie/laravel-activitylog:
- Customer creation/update/deletion
- Credit charges
- Payments received
- Credit limit adjustments
- Reminders sent

## Example Usage

### Create Customer
```json
POST /api/v1/customers
{
  "name": "Juan dela Cruz Hardware",
  "type": "regular",
  "phone": "09171234567",
  "email": "juan@example.com",
  "address": "123 Main St, Manila",
  "credit_limit": 10000000,
  "credit_terms_days": 30,
  "business_name": "JDC Hardware Supply",
  "is_active": true
}
```

### Record Payment (FIFO)
```json
POST /api/v1/customers/{uuid}/payments
{
  "amount": 3000000,
  "payment_method": "cash",
  "notes": "Payment for multiple invoices"
}
```

### Record Payment (Specific Invoices)
```json
POST /api/v1/customers/{uuid}/payments
{
  "amount": 1500000,
  "payment_method": "bank_transfer",
  "reference_number": "BT-20240209-001",
  "invoice_ids": [
    "550e8400-e29b-41d4-a716-446655440000",
    "550e8400-e29b-41d4-a716-446655440001"
  ]
}
```

### Payment Allocation Example
Customer has 3 outstanding invoices:
- INV-001: ₱10,000 (due Jan 1)
- INV-002: ₱15,000 (due Jan 5)
- INV-003: ₱20,000 (due Jan 10)

Customer pays ₱30,000:
```json
{
  "transaction": {...},
  "applied_to": [
    {
      "sale_uuid": "...",
      "sale_number": "INV-001",
      "amount_applied": 10000.00,
      "previous_balance": 10000.00,
      "new_balance": 0.00,
      "status": "paid"
    },
    {
      "sale_uuid": "...",
      "sale_number": "INV-002",
      "amount_applied": 15000.00,
      "previous_balance": 15000.00,
      "new_balance": 0.00,
      "status": "paid"
    },
    {
      "sale_uuid": "...",
      "sale_number": "INV-003",
      "amount_applied": 5000.00,
      "previous_balance": 20000.00,
      "new_balance": 15000.00,
      "status": "partially_paid"
    }
  ],
  "remaining_credit": 0.00
}
```

### Get Aging Report
```
GET /api/v1/customers/credit/aging
```

Response:
```json
{
  "customers": [
    {
      "customer": {
        "uuid": "...",
        "name": "Juan dela Cruz Hardware",
        "credit_limit": 100000.00,
        "total_outstanding": 45000.00
      },
      "aging": {
        "current": 15000.00,
        "days_31_60": 10000.00,
        "days_61_90": 15000.00,
        "days_over_90": 5000.00
      },
      "total_outstanding": 45000.00,
      "credit_utilization": 45.00
    }
  ],
  "summary": {
    "current": 150000.00,
    "days_31_60": 85000.00,
    "days_61_90": 45000.00,
    "days_over_90": 25000.00,
    "total_outstanding": 305000.00
  },
  "customer_count": 12
}
```

### Get Customer Statement
```
GET /api/v1/customers/{uuid}/statement?start_date=2024-01-01&end_date=2024-01-31
```

### Download Statement PDF
```
GET /api/v1/customers/{uuid}/statement/pdf?start_date=2024-01-01&end_date=2024-01-31
```

## Database Considerations

### Indexing Recommendations
```sql
-- Customer table
CREATE INDEX idx_customers_type ON customers(type);
CREATE INDEX idx_customers_total_outstanding ON customers(total_outstanding);
CREATE INDEX idx_customers_is_active ON customers(is_active);

-- Credit transactions table
CREATE INDEX idx_credit_transactions_customer_id ON credit_transactions(customer_id);
CREATE INDEX idx_credit_transactions_type ON credit_transactions(type);
CREATE INDEX idx_credit_transactions_due_date ON credit_transactions(due_date);
CREATE INDEX idx_credit_transactions_paid_date ON credit_transactions(paid_date);
CREATE INDEX idx_credit_transactions_transaction_date ON credit_transactions(transaction_date);
```

### Data Integrity
- Use database transactions for all credit operations
- Soft deletes for customers (can be restored)
- Foreign key constraints with appropriate actions
- Prevent deletion if outstanding balance > 0

## Testing Recommendations

### Unit Tests
- CreditService methods
- Payment allocation logic
- Aging calculation
- Balance calculations

### Feature Tests
- Customer CRUD operations
- Payment recording with FIFO
- Payment recording with specific invoices
- Credit limit adjustments
- Statement generation
- Aging report accuracy

### Integration Tests
- Full payment flow
- Credit sale + payment cycle
- Overdue detection
- PDF generation

## Future Enhancements

1. **SMS/Email Integration**
   - Implement sendReminder() with actual SMS/email providers
   - Automated overdue reminders (daily cron)
   - Payment confirmation notifications

2. **Excel Export**
   - Implement export() using Laravel Excel
   - Support multiple formats (XLSX, CSV)
   - Include filters and custom columns

3. **Credit Alerts**
   - Real-time credit limit warnings
   - Approaching limit notifications
   - Overdue account alerts

4. **Payment Plans**
   - Installment payment tracking
   - Scheduled payments
   - Payment plan management

5. **Credit Scoring**
   - Payment history tracking
   - Automatic credit limit adjustments
   - Risk assessment

6. **Multi-currency Support**
   - Support multiple currencies
   - Exchange rate tracking
   - Currency conversion

7. **Advanced Reporting**
   - Collections effectiveness
   - DSO (Days Sales Outstanding)
   - Bad debt provisions
   - Credit utilization trends

## Security Considerations

1. **Authorization**
   - Implement role-based access control
   - Restrict credit limit adjustments to managers
   - Audit all credit operations

2. **Data Protection**
   - Encrypt sensitive customer data
   - Secure PDF generation
   - Protect customer financial information

3. **API Rate Limiting**
   - Implement rate limiting for payment endpoints
   - Prevent abuse of credit checking

4. **Input Validation**
   - Comprehensive validation in Form Requests
   - Sanitize all user inputs
   - Validate business rules

## Support and Maintenance

For issues or questions, contact the development team.

**Last Updated**: February 9, 2026
**Version**: 1.0.0
**Author**: HardwarePOS Development Team
