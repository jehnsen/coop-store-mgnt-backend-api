# Settings & Configuration API Documentation

Complete documentation for the Settings and Configuration API endpoints in the HardwarePOS system.

## Table of Contents

1. [Store Profile Management](#store-profile-management)
2. [User Management](#user-management)
3. [Branch Management](#branch-management)
4. [Permission Management](#permission-management)
5. [Payment Methods Configuration](#payment-methods-configuration)
6. [Receipt Template](#receipt-template)
7. [Tax Settings](#tax-settings)
8. [Credit Settings](#credit-settings)
9. [System Settings](#system-settings)

---

## Authentication

All endpoints require authentication via Bearer token:

```
Authorization: Bearer {token}
```

Most endpoints also require `owner` or `manager` role access.

---

## Store Profile Management

### Get Store Profile

Retrieve the current store settings and configuration.

**Endpoint:** `GET /api/v1/settings/store`

**Response:**
```json
{
  "success": true,
  "data": {
    "store": {
      "name": "JM Hardware & Construction Supply",
      "address": "123 Main St, Brgy. Commonwealth",
      "city": "Quezon City",
      "province": "Metro Manila",
      "postal_code": "1121",
      "phone": "02-8123-4567",
      "email": "info@jmhardware.ph",
      "website": "https://jmhardware.ph",
      "tin": "123-456-789-000",
      "bir_permit": "FP-123-2026",
      "vat_registered": true,
      "logo_url": "/storage/logos/store_logo_1_1234567890.png"
    },
    "stats": {
      "total_users": 15,
      "active_users": 12,
      "total_branches": 3,
      "storage_used": 125.45
    },
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

### Update Store Profile

Update store details and configuration.

**Endpoint:** `PUT /api/v1/settings/store`

**Request Body:**
```json
{
  "name": "JM Hardware & Construction Supply",
  "address": "123 Main St, Brgy. Commonwealth",
  "city": "Quezon City",
  "province": "Metro Manila",
  "postal_code": "1121",
  "phone": "02-8123-4567",
  "email": "info@jmhardware.ph",
  "website": "https://jmhardware.ph",
  "tin": "123-456-789-000",
  "bir_permit": "FP-123-2026",
  "vat_registered": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Store profile updated successfully"
}
```

### Upload Store Logo

Upload a logo image for the store.

**Endpoint:** `POST /api/v1/settings/store/logo`

**Request:** `multipart/form-data`
- `logo`: Image file (jpeg, png, jpg, gif, max 2MB)

**Response:**
```json
{
  "success": true,
  "message": "Logo uploaded successfully",
  "data": {
    "logo_url": "/storage/logos/store_logo_1_1234567890.png"
  }
}
```

### Delete Store Logo

Remove the store logo.

**Endpoint:** `DELETE /api/v1/settings/store/logo`

**Response:**
```json
{
  "success": true,
  "message": "Logo deleted successfully"
}
```

---

## User Management

### List Users

Get paginated list of users with filters.

**Endpoint:** `GET /api/v1/settings/users`

**Query Parameters:**
- `per_page` (int, default: 15): Items per page
- `search` (string): Search by name, email, or phone
- `role` (string): Filter by role (owner, manager, cashier, inventory_staff)
- `is_active` (boolean): Filter by active status

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "09171234567",
      "role": "manager",
      "is_active": true,
      "branch": {
        "id": 1,
        "uuid": "650e8400-e29b-41d4-a716-446655440000",
        "name": "Main Branch"
      },
      "last_login_at": "2024-01-15T10:30:00.000000Z",
      "status": "active",
      "role_display": "Manager",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 45
  }
}
```

### Create User

Create a new user account.

**Endpoint:** `POST /api/v1/settings/users`

**Request Body:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "SecureP@ss123!",
  "role": "cashier",
  "branch_id": 1,
  "phone": "09171234567",
  "is_active": true
}
```

**Validation Rules:**
- `name`: required, string, max 255
- `email`: required, email, unique within store
- `password`: required, min 8 chars, must include mixed case, numbers, symbols
- `role`: required, one of: owner, manager, cashier, inventory_staff
- `branch_id`: optional, must exist
- `phone`: optional, string, max 50
- `is_active`: optional, boolean

**Response:**
```json
{
  "success": true,
  "message": "User created successfully",
  "data": {
    "id": 2,
    "uuid": "750e8400-e29b-41d4-a716-446655440000",
    "name": "Jane Smith",
    "email": "jane@example.com",
    "role": "cashier",
    "is_active": true
  }
}
```

### Get User Details

Get detailed information about a specific user.

**Endpoint:** `GET /api/v1/settings/users/{uuid}`

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 2,
      "uuid": "750e8400-e29b-41d4-a716-446655440000",
      "name": "Jane Smith",
      "email": "jane@example.com",
      "role": "cashier",
      "is_active": true,
      "branch": {
        "id": 1,
        "uuid": "650e8400-e29b-41d4-a716-446655440000",
        "name": "Main Branch"
      }
    },
    "permissions": [
      "products.view",
      "sales.view",
      "sales.create"
    ],
    "recent_activity": [
      {
        "action": "create_sale",
        "description": "Created sale #S-2024-001",
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ]
  }
}
```

### Update User

Update user details.

**Endpoint:** `PUT /api/v1/settings/users/{uuid}`

**Request Body:**
```json
{
  "name": "Jane Smith Updated",
  "email": "jane.updated@example.com",
  "role": "manager",
  "branch_id": 2,
  "phone": "09181234567",
  "is_active": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "id": 2,
    "uuid": "750e8400-e29b-41d4-a716-446655440000",
    "name": "Jane Smith Updated",
    "email": "jane.updated@example.com"
  }
}
```

### Deactivate User

Set user's is_active status to false and revoke all tokens.

**Endpoint:** `POST /api/v1/settings/users/{uuid}/deactivate`

**Response:**
```json
{
  "success": true,
  "message": "User deactivated successfully"
}
```

### Activate User

Set user's is_active status to true.

**Endpoint:** `POST /api/v1/settings/users/{uuid}/activate`

**Response:**
```json
{
  "success": true,
  "message": "User activated successfully"
}
```

### Delete User

Soft delete a user (with dependency checks).

**Endpoint:** `DELETE /api/v1/settings/users/{uuid}`

**Response:**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

**Error Response (if has transactions):**
```json
{
  "success": false,
  "message": "Cannot delete user with existing transactions. Consider deactivating instead."
}
```

### Reset User Password

Admin-initiated password reset.

**Endpoint:** `POST /api/v1/settings/users/{uuid}/reset-password`

**Request Body:**
```json
{
  "new_password": "NewSecureP@ss123!",
  "new_password_confirmation": "NewSecureP@ss123!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

---

## Branch Management

### List Branches

Get all branches for the current store.

**Endpoint:** `GET /api/v1/settings/branches`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "650e8400-e29b-41d4-a716-446655440000",
      "name": "Main Branch",
      "address": "123 Main St",
      "city": "Quezon City",
      "phone": "02-8123-4567",
      "is_main": true,
      "is_active": true,
      "users_count": 10,
      "status": "active",
      "type": "main",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

### Create Branch

Create a new branch location.

**Endpoint:** `POST /api/v1/settings/branches`

**Request Body:**
```json
{
  "name": "Cubao Branch",
  "address": "456 Aurora Blvd",
  "city": "Quezon City",
  "phone": "02-8765-4321",
  "is_main": false,
  "is_active": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Branch created successfully",
  "data": {
    "id": 2,
    "uuid": "850e8400-e29b-41d4-a716-446655440000",
    "name": "Cubao Branch"
  }
}
```

### Update Branch

Update branch details.

**Endpoint:** `PUT /api/v1/settings/branches/{uuid}`

**Request Body:**
```json
{
  "name": "Cubao Main Branch",
  "address": "456 Aurora Blvd, Updated",
  "is_active": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Branch updated successfully",
  "data": {
    "id": 2,
    "uuid": "850e8400-e29b-41d4-a716-446655440000",
    "name": "Cubao Main Branch"
  }
}
```

### Delete Branch

Soft delete a branch (with dependency checks).

**Endpoint:** `DELETE /api/v1/settings/branches/{uuid}`

**Response:**
```json
{
  "success": true,
  "message": "Branch deleted successfully"
}
```

**Error Response (if has users):**
```json
{
  "success": false,
  "message": "Cannot delete branch with assigned users. Reassign users first."
}
```

---

## Permission Management

### Get All Permissions

Get all available permissions grouped by module.

**Endpoint:** `GET /api/v1/settings/permissions`

**Response:**
```json
{
  "success": true,
  "data": {
    "products": {
      "products.view": "View products",
      "products.create": "Create products",
      "products.edit": "Edit products",
      "products.delete": "Delete products",
      "products.adjust_stock": "Adjust stock levels"
    },
    "sales": {
      "sales.view": "View sales",
      "sales.create": "Create sales",
      "sales.void": "Void sales",
      "sales.refund": "Process refunds"
    },
    "customers": {
      "customers.view": "View customers",
      "customers.create": "Create customers",
      "customers.edit": "Edit customers",
      "customers.delete": "Delete customers",
      "customers.manage_credit": "Manage customer credit"
    }
  }
}
```

### Get User Permissions

Get specific user's permission list.

**Endpoint:** `GET /api/v1/settings/permissions/user/{userId}`

**Response:**
```json
{
  "success": true,
  "data": {
    "user_id": 2,
    "role": "cashier",
    "permissions": [
      "products.view",
      "sales.view",
      "sales.create",
      "customers.view"
    ]
  }
}
```

### Update User Permissions

Update a user's custom permissions.

**Endpoint:** `PUT /api/v1/settings/permissions/user/{userId}`

**Request Body:**
```json
{
  "permissions": [
    "products.view",
    "sales.view",
    "sales.create",
    "sales.void",
    "customers.view"
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "User permissions updated successfully"
}
```

### Get Role Permissions

Get default permissions for a role.

**Endpoint:** `GET /api/v1/settings/permissions/role/{role}`

**Response:**
```json
{
  "success": true,
  "data": {
    "role": "cashier",
    "permissions": [
      "products.view",
      "sales.view",
      "sales.create",
      "customers.view",
      "reports.view_sales"
    ]
  }
}
```

### Update Role Permissions

Update default permission template for a role.

**Endpoint:** `PUT /api/v1/settings/permissions/role/{role}`

**Request Body:**
```json
{
  "permissions": [
    "products.view",
    "sales.view",
    "sales.create",
    "sales.void",
    "customers.view",
    "reports.view_sales"
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Role permissions updated successfully"
}
```

---

## Payment Methods Configuration

### Get Payment Methods

Get configured payment methods and their status.

**Endpoint:** `GET /api/v1/settings/payment-methods`

**Response:**
```json
{
  "success": true,
  "data": {
    "cash": {
      "enabled": true,
      "name": "Cash"
    },
    "gcash": {
      "enabled": true,
      "name": "GCash",
      "api_key_set": true
    },
    "maya": {
      "enabled": false,
      "name": "Maya (PayMaya)",
      "api_key_set": false
    },
    "card": {
      "enabled": true,
      "name": "Credit/Debit Card"
    },
    "bank_transfer": {
      "enabled": false,
      "name": "Bank Transfer"
    },
    "credit": {
      "enabled": true,
      "name": "Credit Account"
    }
  }
}
```

### Update Payment Methods

Enable/disable payment methods and configure API keys.

**Endpoint:** `PUT /api/v1/settings/payment-methods`

**Request Body:**
```json
{
  "gcash_enabled": true,
  "gcash_api_key": "sk_test_1234567890abcdef",
  "maya_enabled": true,
  "maya_api_key": "pk_test_0987654321fedcba",
  "card_enabled": true,
  "bank_enabled": false,
  "credit_enabled": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment methods updated successfully"
}
```

**Note:** API keys are encrypted before storage.

---

## Receipt Template

### Get Receipt Template

Get current receipt template settings.

**Endpoint:** `GET /api/v1/settings/receipt-template`

**Response:**
```json
{
  "success": true,
  "data": {
    "header_text": "Thank you for shopping with us!",
    "footer_text": "This serves as your official receipt.",
    "show_logo": true,
    "paper_width": 80,
    "show_bir_info": true,
    "show_cashier": true,
    "show_customer": true
  }
}
```

### Update Receipt Template

Update receipt template configuration.

**Endpoint:** `PUT /api/v1/settings/receipt-template`

**Request Body:**
```json
{
  "header_text": "Thank you for your purchase!",
  "footer_text": "Official receipt. For concerns, call 02-8123-4567",
  "show_logo": true,
  "paper_width": 80,
  "show_bir_info": true,
  "show_cashier": true,
  "show_customer": true
}
```

**Validation Rules:**
- `header_text`: optional, max 500 chars
- `footer_text`: optional, max 500 chars
- `paper_width`: optional, must be 58 or 80
- `show_logo`: optional, boolean
- `show_bir_info`: optional, boolean
- `show_cashier`: optional, boolean
- `show_customer`: optional, boolean

**Response:**
```json
{
  "success": true,
  "message": "Receipt template updated successfully"
}
```

### Preview Receipt

Generate a sample receipt with current template.

**Endpoint:** `GET /api/v1/settings/receipt-template/preview`

**Response:**
```json
{
  "success": true,
  "data": {
    "store_name": "JM Hardware & Construction Supply",
    "store_address": "123 Main St",
    "tin": "123-456-789-000",
    "receipt_number": "SAMPLE-001",
    "date": "2024-01-15 10:30:00",
    "cashier": "John Doe",
    "items": [
      {
        "name": "Sample Product 1",
        "qty": 2,
        "price": 150.00,
        "total": 300.00
      }
    ],
    "subtotal": 550.00,
    "tax": 66.00,
    "total": 616.00,
    "header_text": "Thank you for shopping with us!",
    "footer_text": "This serves as your official receipt."
  }
}
```

---

## Tax Settings

### Get Tax Settings

Get current tax configuration.

**Endpoint:** `GET /api/v1/settings/tax`

**Response:**
```json
{
  "success": true,
  "data": {
    "vat_rate": 12,
    "vat_inclusive": true,
    "is_bmbe": false
  }
}
```

### Update Tax Settings

Update tax configuration.

**Endpoint:** `PUT /api/v1/settings/tax`

**Request Body:**
```json
{
  "vat_rate": 12,
  "vat_inclusive": true,
  "is_bmbe": false
}
```

**Validation Rules:**
- `vat_rate`: optional, numeric, 0-100
- `vat_inclusive`: optional, boolean
- `is_bmbe`: optional, boolean (Barangay Micro Business Enterprise - exempt from VAT)

**Response:**
```json
{
  "success": true,
  "message": "Tax settings updated successfully"
}
```

---

## Credit Settings

### Get Credit Settings

Get default credit terms and limits.

**Endpoint:** `GET /api/v1/settings/credit`

**Response:**
```json
{
  "success": true,
  "data": {
    "default_credit_limit": 50000,
    "default_terms_days": 30,
    "reminder_days_before": 3
  }
}
```

### Update Credit Settings

Update credit default settings.

**Endpoint:** `PUT /api/v1/settings/credit`

**Request Body:**
```json
{
  "default_credit_limit": 75000,
  "default_terms_days": 45,
  "reminder_days_before": 5
}
```

**Validation Rules:**
- `default_credit_limit`: optional, integer, min 0
- `default_terms_days`: optional, integer, 1-365
- `reminder_days_before`: optional, integer, 0-30

**Response:**
```json
{
  "success": true,
  "message": "Credit settings updated successfully"
}
```

---

## System Settings

### Get System Settings

Get system-wide preferences.

**Endpoint:** `GET /api/v1/settings/system`

**Response:**
```json
{
  "success": true,
  "data": {
    "app_version": "1.0.0",
    "timezone": "Asia/Manila",
    "currency": "PHP",
    "date_format": "Y-m-d",
    "time_format": "H:i:s",
    "number_format": "en_PH"
  }
}
```

### Update System Settings

Update system preferences.

**Endpoint:** `PUT /api/v1/settings/system`

**Request Body:**
```json
{
  "timezone": "Asia/Manila",
  "currency": "PHP",
  "date_format": "Y-m-d",
  "time_format": "H:i:s",
  "number_format": "en_PH"
}
```

**Response:**
```json
{
  "success": true,
  "message": "System settings updated successfully"
}
```

### Clear Cache

Clear application cache, config cache, and route cache.

**Endpoint:** `POST /api/v1/settings/system/clear-cache`

**Response:**
```json
{
  "success": true,
  "message": "Cache cleared successfully"
}
```

---

## Error Responses

All endpoints may return error responses in the following format:

### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

### Forbidden (403)
```json
{
  "success": false,
  "message": "You do not have permission to perform this action"
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Failed to retrieve user details",
  "error": "No query results for model..."
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to update store profile",
  "error": "Database connection error"
}
```

---

## Security Features

1. **Permission-Based Access**: All settings endpoints require owner/manager role
2. **Audit Logging**: All settings changes are logged to activity_logs
3. **API Key Encryption**: Payment gateway API keys are encrypted using Laravel's encryption
4. **Password Requirements**: Enforced complexity with minimum 8 characters, mixed case, numbers, symbols
5. **Role Validation**: Users cannot assign higher roles than their own
6. **Dependency Checks**: Prevent deletion if dependencies exist
7. **Token Revocation**: Deactivating users revokes all their tokens

---

## Default Role Permissions

### Owner
- All permissions (full access)

### Manager
- All product, sales, customer, and inventory permissions
- View-only access to reports
- No user/branch/permission management

### Cashier
- View products
- Create and view sales
- View customers
- View sales reports

### Inventory Staff
- View and edit products
- Adjust stock levels
- All inventory operations
- View inventory reports

---

## Notes

1. **Settings Storage**: Settings are stored in the `settings` table with store-scoped keys
2. **Cache Management**: Settings are cached and automatically cleared when updated
3. **Logo Storage**: Logos are stored in `storage/app/public/logos`
4. **Permission Wildcards**: Use `*` for all permissions (e.g., `products.*` for all product permissions)
5. **Branch Requirements**: At least one branch must exist and be marked as `is_main`
6. **User Activity**: Last login time and IP are tracked automatically
