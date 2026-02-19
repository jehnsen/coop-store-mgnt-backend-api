# HardwarePOS - Complete Postman Collection

This directory contains a complete Postman collection for the **HardwarePOS Cloud-based Point of Sale API**.

## 📦 Files Included

1. **HardwarePOS_API_Collection.postman_collection.json** - Complete API collection with 150+ endpoints
2. **HardwarePOS_Environment.postman_environment.json** - Environment variables configuration
3. **POSTMAN_COLLECTION_README.md** - This documentation file

## 🚀 Getting Started

### Step 1: Import Collection & Environment

1. Open **Postman** desktop app or web version
2. Click **Import** button (top left)
3. Drag and drop both JSON files:
   - `HardwarePOS_API_Collection.postman_collection.json`
   - `HardwarePOS_Environment.postman_environment.json`
4. Click **Import**

### Step 2: Select Environment

1. Click the environment dropdown (top right)
2. Select **"HardwarePOS - Local Development"**

### Step 3: Configure Base URL

1. Click the environment quick look icon (eye icon)
2. Edit the `base_url` variable if needed (default: `http://localhost`)
3. For production, create a new environment with your production URL

### Step 4: Login & Get Token

1. Expand the collection: **HardwarePOS - Cloud-based POS API**
2. Go to **1. Authentication** → **Login**
3. Update the request body with valid credentials:
   ```json
   {
       "email": "admin@hardwarepos.com",
       "password": "password123"
   }
   ```
4. Click **Send**
5. The token will be **automatically saved** to the `token` variable (via test script)

### Step 5: Start Making Requests

All endpoints are now ready to use! The Bearer token is automatically applied to all requests.

---

## 📚 Collection Structure

The collection is organized into **12 main folders**:

### 1. Authentication (7 endpoints)
- Login (saves token automatically)
- Get authenticated user
- Update profile
- Change password
- Forgot password
- Reset password
- Logout

### 2. Products (11 endpoints)
- CRUD operations
- Search (POS optimized)
- Barcode lookup
- Stock adjustments
- Stock history
- Low stock alerts
- Bulk updates

### 3. Categories (6 endpoints)
- CRUD operations
- Category reordering

### 4. Units of Measure (5 endpoints)
- CRUD operations for units (pcs, box, kg, etc.)

### 5. Sales (POS) (12 endpoints)
- Complete sales management
- Create sale/transaction
- Void and refund
- Receipt generation (JSON & PDF)
- Send receipt (email/SMS)
- Hold/resume transactions
- Held transaction management

### 6. Customers (14 endpoints)
- Customer CRUD
- Credit management
- Payment recording (with FIFO allocation)
- Credit limit adjustments
- Customer statements (JSON & PDF)
- Payment reminders
- Credit aging & overdue reports
- Export to Excel

### 7. Dashboard (10 endpoints)
- Summary statistics
- Sales trends
- Top products & customers
- Sales by category
- Credit aging
- Recent transactions
- Stock alerts
- Upcoming deliveries
- Comprehensive dashboard data

### 8. Suppliers (9 endpoints)
- Supplier CRUD
- Product-supplier linking
- Price management
- Price history tracking

### 9. Purchase Orders (9 endpoints)
- PO CRUD operations
- Status workflow (draft → submitted → received → cancelled)
- Receive PO (auto stock update)
- Download PO as PDF

### 10. Deliveries (12 endpoints)
- Delivery CRUD
- Today's schedule
- Status tracking (pending → in_transit → delivered)
- Proof of delivery (photo/signature upload)
- Receipt generation
- Driver assignment

### 11. Reports (15 endpoints)
Organized into 4 sub-categories:
- **Sales Reports**: Daily, summary, by category, by customer, by payment method, by cashier
- **Inventory Reports**: Valuation, movement, low stock, dead stock, profitability
- **Credit Reports**: Aging, collection
- **Purchase Reports**: By supplier, price comparison

### 12. Settings (30+ endpoints)
Complete system configuration:
- **Store Settings**: Profile, logo upload
- **User Management**: CRUD, activate/deactivate, password reset
- **Branch Management**: Multi-branch support
- **Permissions**: Role-based & user-specific permissions
- **Payment Methods**: Configure available payment options
- **Receipt Template**: Customize receipt appearance
- **Tax Settings**: VAT configuration
- **Credit Settings**: Default credit limits & terms
- **System Settings**: Timezone, currency, date format, cache management

---

## 🔐 Authentication

### How It Works

The API uses **Laravel Sanctum** for token-based authentication:

1. **Login** via `/auth/login` with email & password
2. Receive a **Bearer token** in the response
3. Token is automatically saved to collection variable
4. All subsequent requests include: `Authorization: Bearer {token}`

### Token Auto-Save

The **Login** request includes a test script that automatically extracts and saves the token:

```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    if (jsonData.data && jsonData.data.token) {
        pm.collectionVariables.set('token', jsonData.data.token);
    }
}
```

You don't need to manually copy/paste tokens!

### Manual Token Configuration (Optional)

If needed, you can manually set the token:

1. Click the environment quick look icon (eye icon)
2. Edit the `token` variable
3. Paste your token value

---

## 💰 Important: Monetary Values

**All monetary amounts are stored in CENTAVOS (integers) for precision:**

- **1 Peso = 100 centavos**
- **₱250.00 = 25000 centavos**
- **₱10,000.00 = 1000000 centavos**

### Examples:

**Creating a Product:**
```json
{
    "name": "Hammer",
    "cost_price": 20000,    // ₱200.00
    "retail_price": 35000   // ₱350.00
}
```

**Recording a Sale:**
```json
{
    "items": [
        {
            "product_id": "uuid",
            "quantity": 2,
            "price": 35000  // ₱350.00 per item
        }
    ],
    "amount_paid": 100000   // ₱1,000.00
}
```

**API responses typically return amounts in pesos for display.**

---

## 🏢 Multi-Tenancy

The system is **multi-tenant** with store-level data isolation:

- Each user belongs to a **store**
- The `store.access` middleware automatically filters all data by `store_id`
- You can only access data from your own store
- No manual filtering required in requests

---

## 📋 Query Parameters Guide

### Common Query Parameters

**Pagination:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15)

**Search & Filter:**
- `q` - Search query (searches name, SKU, barcode, etc.)
- `search` - Alternative search parameter
- `status` - Filter by status
- `is_active` - Filter active/inactive (1 or 0)

**Sorting:**
- `sort_by` - Field to sort by (e.g., "name", "created_at")
- `sort_order` - Sort direction ("asc" or "desc")

**Date Ranges:**
- `date` - Single date (YYYY-MM-DD)
- `start_date` - Range start (YYYY-MM-DD)
- `end_date` - Range end (YYYY-MM-DD)
- `date_from` - Alternative range start
- `date_to` - Alternative range end

**Reports:**
- `days` - Number of days (1-365)
- `limit` - Number of items (1-50)
- `group_by` - Grouping (day, week, month)
- `export` - Export format (pdf, excel)

---

## 📤 Standard Response Formats

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        // Response data here
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
    "message": "Error message here",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

---

## 🔢 HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized (authentication required) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found |
| 422 | Validation Error (unprocessable entity) |
| 500 | Internal Server Error |

---

## 🛒 Common Workflows

### 1. Point of Sale Flow

```
1. Search Product → GET /products/search?q=hammer
2. Scan Barcode → GET /products/barcode/{barcode}
3. Create Sale → POST /sales
   {
       "items": [...],
       "payment_method": "cash",
       "amount_paid": 100000
   }
4. Get Receipt → GET /sales/{uuid}/receipt/pdf
5. (Optional) Send Receipt → POST /sales/{uuid}/receipt/send
```

### 2. Credit Sales & Payment Flow

```
1. Create Customer → POST /customers
   {
       "name": "Juan Hardware",
       "credit_limit": 10000000,
       "credit_terms_days": 30
   }

2. Create Credit Sale → POST /sales
   {
       "customer_id": "customer-uuid",
       "items": [...],
       "payment_method": "credit"
   }

3. Record Payment → POST /customers/{uuid}/payments
   {
       "amount": 5000000,
       "payment_method": "gcash",
       "invoice_ids": ["sale-uuid-1", "sale-uuid-2"]
   }

4. Get Statement → GET /customers/{uuid}/statement/pdf
```

### 3. Inventory Management Flow

```
1. Create Product → POST /products
2. Receive Stock → POST /products/{uuid}/adjust-stock
   {
       "quantity": 100,
       "type": "add",
       "reason": "Stock replenishment"
   }
3. Check Low Stock → GET /products/low-stock
4. View Stock History → GET /products/{uuid}/stock-history
```

### 4. Purchase Order Flow

```
1. Create PO → POST /purchase-orders (status: draft)
2. Submit PO → POST /purchase-orders/{uuid}/submit
3. Receive PO → POST /purchase-orders/{uuid}/receive
   (automatically updates product stock)
4. Download PO → GET /purchase-orders/{uuid}/pdf
```

### 5. Delivery Management Flow

```
1. Create Delivery → POST /deliveries
2. Assign Driver → POST /deliveries/{uuid}/assign-driver
3. Update Status → PUT /deliveries/{uuid}/status
   {"status": "in_transit"}
4. Upload Proof → POST /deliveries/{uuid}/proof
   (photo/signature)
5. Mark Delivered → PUT /deliveries/{uuid}/status
   {"status": "delivered"}
```

---

## 🎯 Sample Data Examples

### Create Product
```json
{
    "name": "Hammer - Claw Type 16oz",
    "sku": "HAM-016",
    "barcode": "1234567890123",
    "category_id": "cat-uuid-here",
    "unit_id": "unit-uuid-here",
    "cost_price": 25000,
    "retail_price": 35000,
    "current_stock": 100,
    "reorder_point": 20,
    "description": "Professional grade claw hammer",
    "is_active": true
}
```

### Create Customer
```json
{
    "name": "Juan dela Cruz Hardware",
    "type": "regular",
    "phone": "09171234567",
    "email": "juan@example.com",
    "address": "123 Main Street, Quezon City",
    "credit_limit": 10000000,
    "credit_terms_days": 30,
    "business_name": "JDC Hardware Supplies",
    "is_active": true
}
```

### Create Sale (POS Transaction)
```json
{
    "customer_id": "optional-customer-uuid",
    "items": [
        {
            "product_id": "product-uuid-1",
            "quantity": 2,
            "price": 35000
        },
        {
            "product_id": "product-uuid-2",
            "quantity": 1,
            "price": 15000
        }
    ],
    "payment_method": "gcash",
    "amount_paid": 100000,
    "notes": "Customer requested gift wrapping"
}
```

### Record Payment (Credit Customer)
```json
{
    "amount": 5000000,
    "payment_method": "gcash",
    "reference_number": "GC123456789",
    "payment_date": "2024-02-09",
    "invoice_ids": ["sale-uuid-1", "sale-uuid-2"],
    "notes": "Payment via GCash - Transaction #GC123456789"
}
```

---

## 🔍 Search & Filter Examples

### Product Search
```
GET /products?q=hammer&category_id=uuid&is_active=1&low_stock=1
GET /products/search?q=ham  (POS fast search)
GET /products/barcode/1234567890123
```

### Sales Filtering
```
GET /sales?status=completed&date_from=2024-01-01&date_to=2024-12-31
GET /sales?customer_id=uuid&search=INV-2024
```

### Customer Filtering
```
GET /customers?type=regular&has_outstanding_balance=1
GET /customers?q=juan&is_active=1
GET /customers/credit/overdue
```

---

## 📊 Reports Usage

### Sales Reports
```
GET /reports/sales/daily?date=2024-02-09
GET /reports/sales/summary?start_date=2024-01-01&end_date=2024-12-31&group_by=month
GET /reports/sales/by-category?start_date=2024-01-01&end_date=2024-12-31
GET /reports/sales/by-payment-method?start_date=2024-01-01&end_date=2024-12-31
```

### Inventory Reports
```
GET /reports/inventory/valuation
GET /reports/inventory/movement?start_date=2024-01-01&end_date=2024-12-31
GET /reports/inventory/low-stock
GET /reports/inventory/dead-stock?days=90
GET /reports/inventory/profitability?start_date=2024-01-01&end_date=2024-12-31&limit=20
```

### Credit Reports
```
GET /reports/credit/aging
GET /reports/credit/collection?start_date=2024-01-01&end_date=2024-12-31
```

---

## ⚙️ Settings Configuration

### Payment Methods Configuration
```json
{
    "methods": [
        {"code": "cash", "name": "Cash", "is_active": true},
        {"code": "gcash", "name": "GCash", "is_active": true},
        {"code": "maya", "name": "Maya", "is_active": true},
        {"code": "bank_transfer", "name": "Bank Transfer", "is_active": false},
        {"code": "check", "name": "Check", "is_active": false}
    ]
}
```

### Tax Settings
```json
{
    "vat_rate": 12,
    "is_vat_inclusive": true,
    "tax_type": "vat"
}
```

### Credit Settings
```json
{
    "default_credit_limit": 5000000,
    "default_terms_days": 30,
    "auto_send_reminders": true,
    "reminder_days_before": 3
}
```

---

## 🛡️ Security & Permissions

### Permission System

The API uses role-based permissions. Common permissions include:

- `view_products`, `create_products`, `edit_products`, `delete_products`
- `view_sales`, `create_sales`, `void_sales`, `refund_sales`
- `view_customers`, `create_customers`, `edit_customers`
- `view_reports`, `export_reports`
- `manage_settings`, `manage_users`, `manage_permissions`

### User Roles

Typical roles include:
- **admin** - Full access
- **manager** - Sales, inventory, reports
- **cashier** - POS operations only
- **inventory_clerk** - Stock management

---

## 📝 Additional Notes

### Customer Types
- `walk_in` - One-time customers
- `regular` - Recurring customers
- `contractor` - Construction contractors (often credit)
- `government` - Government entities

### Sale Statuses
- `completed` - Successfully processed
- `voided` - Cancelled/voided
- `refunded` - Full or partial refund

### Purchase Order Statuses
- `draft` - Being created (can edit/delete)
- `submitted` - Sent to supplier
- `received` - Received and stock updated
- `cancelled` - Cancelled

### Delivery Statuses
- `pending` - Scheduled, not yet dispatched
- `in_transit` - Out for delivery
- `delivered` - Successfully delivered
- `cancelled` - Cancelled

### Credit Aging Buckets
- **Current**: 0-30 days
- **31-60 days**: Slightly overdue
- **61-90 days**: Overdue
- **Over 90 days**: Severely overdue

---

## 🐛 Troubleshooting

### Token Not Saving
If the token doesn't auto-save after login:
1. Check that you're using the **Login** request from the collection
2. Verify the response has `data.token` field
3. Manually copy token from response and set it in environment variables

### 401 Unauthorized Error
- Token expired or invalid
- Run **Login** again to get a fresh token
- Check that environment is selected (top right dropdown)

### 403 Forbidden Error
- User lacks required permission
- Contact admin to update user permissions
- Use `/settings/permissions` endpoints to manage permissions

### 422 Validation Error
- Check request body format
- Review validation errors in response
- Ensure all required fields are provided
- Verify data types (e.g., amounts in centavos as integers)

### Base URL Issues
- Verify `base_url` in environment matches your server
- Ensure Laravel app is running (`php artisan serve`)
- Check for CORS issues if using web Postman

---

## 🎓 Learning Resources

### Postman Documentation
- [Postman Learning Center](https://learning.postman.com/)
- [Variables in Postman](https://learning.postman.com/docs/sending-requests/variables/)
- [Test Scripts](https://learning.postman.com/docs/writing-scripts/test-scripts/)

### Laravel Sanctum
- [Laravel Sanctum Docs](https://laravel.com/docs/sanctum)
- [API Token Authentication](https://laravel.com/docs/sanctum#api-token-authentication)

---

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review Laravel logs: `storage/logs/laravel.log`
3. Enable detailed error messages in `.env`:
   ```
   APP_DEBUG=true
   ```
4. Use Postman Console (View → Show Postman Console) to debug requests

---

## ✅ Quick Checklist

Before starting, ensure:

- [ ] Postman installed (desktop or web)
- [ ] Collection and environment imported
- [ ] Environment selected (top right)
- [ ] Base URL configured correctly
- [ ] Laravel application running
- [ ] Database migrated and seeded (if applicable)
- [ ] Logged in and token saved
- [ ] All requests showing Bearer token in Authorization header

---

## 📄 License

This Postman collection is part of the HardwarePOS project.

---

**Last Updated**: February 2024
**API Version**: v1
**Total Endpoints**: 150+
**Framework**: Laravel 11.x with Sanctum Authentication

---

Happy Testing! 🚀
