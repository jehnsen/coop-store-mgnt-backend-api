# Settings & Configuration API Test Plan

Comprehensive test plan for validating the Settings and Configuration API implementation.

## Prerequisites

1. Database migrations run successfully:
   ```bash
   php artisan migrate
   ```

2. Authentication token obtained from login endpoint

3. Test user with `owner` or `manager` role

---

## Test Cases

### 1. Store Profile Management

#### 1.1 Get Store Profile
```bash
curl -X GET "http://localhost:8000/api/v1/settings/store" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with store profile data and stats

#### 1.2 Update Store Profile
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/store" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "JM Hardware & Construction Supply",
    "address": "123 Main St, Brgy. Commonwealth",
    "city": "Quezon City",
    "province": "Metro Manila",
    "postal_code": "1121",
    "phone": "02-8123-4567",
    "email": "info@jmhardware.ph",
    "tin": "123-456-789-000",
    "bir_permit": "FP-123-2026",
    "vat_registered": true
  }'
```

**Expected:** 200 OK with success message

#### 1.3 Upload Store Logo
```bash
curl -X POST "http://localhost:8000/api/v1/settings/store/logo" \
  -H "Authorization: Bearer {token}" \
  -F "logo=@/path/to/logo.png"
```

**Expected:** 200 OK with logo URL

#### 1.4 Delete Store Logo
```bash
curl -X DELETE "http://localhost:8000/api/v1/settings/store/logo" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 200 OK with success message

---

### 2. User Management

#### 2.1 List Users
```bash
curl -X GET "http://localhost:8000/api/v1/settings/users?per_page=10&search=john&role=manager" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with paginated user list

#### 2.2 Create User
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "testuser@example.com",
    "password": "SecureP@ss123!",
    "role": "cashier",
    "phone": "09171234567",
    "is_active": true
  }'
```

**Expected:** 201 Created with user data

#### 2.3 Get User Details
```bash
curl -X GET "http://localhost:8000/api/v1/settings/users/{uuid}" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with user details, permissions, and recent activity

#### 2.4 Update User
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/users/{uuid}" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User Updated",
    "role": "manager"
  }'
```

**Expected:** 200 OK with updated user data

#### 2.5 Deactivate User
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users/{uuid}/deactivate" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 200 OK, user tokens revoked

#### 2.6 Activate User
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users/{uuid}/activate" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 200 OK

#### 2.7 Reset User Password
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users/{uuid}/reset-password" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "new_password": "NewSecureP@ss123!",
    "new_password_confirmation": "NewSecureP@ss123!"
  }'
```

**Expected:** 200 OK, user tokens revoked

#### 2.8 Delete User (No Transactions)
```bash
curl -X DELETE "http://localhost:8000/api/v1/settings/users/{uuid}" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 200 OK (if no transactions) or 422 (if has transactions)

---

### 3. Branch Management

#### 3.1 List Branches
```bash
curl -X GET "http://localhost:8000/api/v1/settings/branches" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with branch list

#### 3.2 Create Branch
```bash
curl -X POST "http://localhost:8000/api/v1/settings/branches" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Cubao Branch",
    "address": "456 Aurora Blvd",
    "city": "Quezon City",
    "phone": "02-8765-4321",
    "is_main": false,
    "is_active": true
  }'
```

**Expected:** 201 Created with branch data

#### 3.3 Update Branch
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/branches/{uuid}" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Cubao Main Branch",
    "is_active": true
  }'
```

**Expected:** 200 OK with updated branch data

#### 3.4 Delete Branch (No Users)
```bash
curl -X DELETE "http://localhost:8000/api/v1/settings/branches/{uuid}" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 200 OK (if no users) or 422 (if has users)

---

### 4. Permission Management

#### 4.1 Get All Permissions
```bash
curl -X GET "http://localhost:8000/api/v1/settings/permissions" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with permissions grouped by module

#### 4.2 Get User Permissions
```bash
curl -X GET "http://localhost:8000/api/v1/settings/permissions/user/{userId}" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with user's permission list

#### 4.3 Update User Permissions
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/permissions/user/{userId}" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": [
      "products.view",
      "sales.view",
      "sales.create",
      "customers.view"
    ]
  }'
```

**Expected:** 200 OK with success message

#### 4.4 Get Role Permissions
```bash
curl -X GET "http://localhost:8000/api/v1/settings/permissions/role/cashier" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with role's default permissions

#### 4.5 Update Role Permissions
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/permissions/role/cashier" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": [
      "products.view",
      "sales.view",
      "sales.create",
      "sales.void",
      "customers.view"
    ]
  }'
```

**Expected:** 200 OK with success message

---

### 5. Payment Methods Configuration

#### 5.1 Get Payment Methods
```bash
curl -X GET "http://localhost:8000/api/v1/settings/payment-methods" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with payment methods status

#### 5.2 Update Payment Methods
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/payment-methods" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "gcash_enabled": true,
    "gcash_api_key": "sk_test_1234567890abcdef",
    "maya_enabled": false,
    "card_enabled": true,
    "credit_enabled": true
  }'
```

**Expected:** 200 OK, API keys encrypted

---

### 6. Receipt Template

#### 6.1 Get Receipt Template
```bash
curl -X GET "http://localhost:8000/api/v1/settings/receipt-template" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with template settings

#### 6.2 Update Receipt Template
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/receipt-template" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "header_text": "Thank you for your purchase!",
    "footer_text": "Official receipt. For concerns, call 02-8123-4567",
    "show_logo": true,
    "paper_width": 80,
    "show_bir_info": true,
    "show_cashier": true,
    "show_customer": true
  }'
```

**Expected:** 200 OK with success message

#### 6.3 Preview Receipt
```bash
curl -X GET "http://localhost:8000/api/v1/settings/receipt-template/preview" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with sample receipt data

---

### 7. Tax Settings

#### 7.1 Get Tax Settings
```bash
curl -X GET "http://localhost:8000/api/v1/settings/tax" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with tax configuration

#### 7.2 Update Tax Settings
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/tax" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "vat_rate": 12,
    "vat_inclusive": true,
    "is_bmbe": false
  }'
```

**Expected:** 200 OK with success message

---

### 8. Credit Settings

#### 8.1 Get Credit Settings
```bash
curl -X GET "http://localhost:8000/api/v1/settings/credit" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with credit defaults

#### 8.2 Update Credit Settings
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/credit" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "default_credit_limit": 75000,
    "default_terms_days": 45,
    "reminder_days_before": 5
  }'
```

**Expected:** 200 OK with success message

---

### 9. System Settings

#### 9.1 Get System Settings
```bash
curl -X GET "http://localhost:8000/api/v1/settings/system" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected:** 200 OK with system preferences

#### 9.2 Update System Settings
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/system" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "timezone": "Asia/Manila",
    "currency": "PHP",
    "date_format": "Y-m-d",
    "time_format": "H:i:s",
    "number_format": "en_PH"
  }'
```

**Expected:** 200 OK with success message

#### 9.3 Clear Cache
```bash
curl -X POST "http://localhost:8000/api/v1/settings/system/clear-cache" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 200 OK with success message

---

## Security Test Cases

### 10.1 Unauthorized Access (No Token)
```bash
curl -X GET "http://localhost:8000/api/v1/settings/store" \
  -H "Accept: application/json"
```

**Expected:** 401 Unauthorized

### 10.2 Forbidden Access (Cashier Role)
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/store" \
  -H "Authorization: Bearer {cashier_token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test"}'
```

**Expected:** 403 Forbidden

### 10.3 Owner Protection
```bash
curl -X DELETE "http://localhost:8000/api/v1/settings/users/{owner_uuid}" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 403 Forbidden (cannot delete owner)

### 10.4 Self-Deactivation Prevention
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users/{own_uuid}/deactivate" \
  -H "Authorization: Bearer {token}"
```

**Expected:** 403 Forbidden (cannot deactivate self)

### 10.5 Role Escalation Prevention
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users" \
  -H "Authorization: Bearer {manager_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test",
    "email": "test@test.com",
    "password": "SecureP@ss123!",
    "role": "owner"
  }'
```

**Expected:** 403 Forbidden (manager cannot create owner)

---

## Validation Test Cases

### 11.1 Invalid Email
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test",
    "email": "invalid-email",
    "password": "SecureP@ss123!",
    "role": "cashier"
  }'
```

**Expected:** 422 Validation Error

### 11.2 Weak Password
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test",
    "email": "test@test.com",
    "password": "weak",
    "role": "cashier"
  }'
```

**Expected:** 422 Validation Error

### 11.3 Duplicate Email
```bash
curl -X POST "http://localhost:8000/api/v1/settings/users" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test",
    "email": "existing@example.com",
    "password": "SecureP@ss123!",
    "role": "cashier"
  }'
```

**Expected:** 422 Validation Error

### 11.4 Invalid VAT Rate
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/tax" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "vat_rate": 150
  }'
```

**Expected:** 422 Validation Error (max 100)

### 11.5 Invalid Paper Width
```bash
curl -X PUT "http://localhost:8000/api/v1/settings/receipt-template" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "paper_width": 100
  }'
```

**Expected:** 422 Validation Error (must be 58 or 80)

---

## Activity Logging Test Cases

### 12.1 Verify Activity Log Creation
After each settings change:

```sql
SELECT * FROM activity_logs
WHERE action LIKE '%settings%'
ORDER BY created_at DESC
LIMIT 10;
```

**Expected:** All settings changes logged with:
- Correct action name
- User ID
- IP address
- Properties (JSON with changes)
- Timestamp

---

## Cache Test Cases

### 13.1 Verify Cache Clear
1. Update a setting
2. Check that cache is cleared
3. Next request should fetch fresh data

```bash
# Update setting
curl -X PUT "http://localhost:8000/api/v1/settings/store" ...

# Verify cache cleared
php artisan tinker
>>> Cache::tags(['settings'])->get('store_profile');
```

**Expected:** null (cache cleared)

---

## Dependency Check Test Cases

### 14.1 Delete User with Transactions
1. Create a user
2. Create a sale with that user
3. Try to delete the user

**Expected:** 422 Error - "Cannot delete user with existing transactions"

### 14.2 Delete Branch with Users
1. Create a branch
2. Assign users to the branch
3. Try to delete the branch

**Expected:** 422 Error - "Cannot delete branch with assigned users"

### 14.3 Delete Main Branch
1. Try to delete a branch marked as `is_main`

**Expected:** 403 Error - "Cannot delete main branch"

---

## Integration Test Cases

### 15.1 Complete User Lifecycle
1. Create user
2. Get user details
3. Update user
4. Update user permissions
5. Deactivate user
6. Activate user
7. Reset password
8. Delete user

**Expected:** All operations succeed in sequence

### 15.2 Complete Branch Lifecycle
1. Create branch
2. Update branch
3. Assign users to branch
4. Reassign users to different branch
5. Delete original branch

**Expected:** All operations succeed in sequence

---

## Performance Test Cases

### 16.1 List Users Performance
- Test with 1000+ users
- Should complete in < 500ms

### 16.2 Permissions Retrieval
- Test getting permissions for user with many permissions
- Should complete in < 100ms

---

## Database Verification

After testing, verify database state:

```sql
-- Check settings table
SELECT * FROM settings WHERE store_id = 1;

-- Check user_permissions table
SELECT * FROM user_permissions WHERE user_id = 2;

-- Check role_permissions table
SELECT * FROM role_permissions WHERE store_id = 1;

-- Check activity_logs for settings changes
SELECT * FROM activity_logs
WHERE action LIKE '%settings%'
ORDER BY created_at DESC;
```

---

## Expected Results Summary

All test cases should:
1. Return correct HTTP status codes
2. Include proper JSON structure in responses
3. Log activities to activity_logs table
4. Clear relevant caches
5. Enforce security and permissions
6. Validate inputs properly
7. Handle errors gracefully
8. Maintain data integrity
