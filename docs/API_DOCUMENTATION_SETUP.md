# API Documentation with Scramble - Setup Guide

## Overview

Scramble has been configured to automatically generate beautiful, interactive OpenAPI documentation for the HardwarePOS API. This provides a web-based interface where you can explore all 150+ endpoints, view request/response schemas, and even test the API directly from the documentation.

---

## What is Scramble?

**Scramble** is an automatic API documentation generator for Laravel. It:

✅ Automatically analyzes your routes and controllers
✅ Generates OpenAPI 3.1.0 specification
✅ Provides interactive "Try It" functionality
✅ Supports Laravel validation rules
✅ Works with Form Requests, Resources, and API responses
✅ Updates automatically as your code changes
✅ Beautiful, modern UI powered by Stoplight Elements

---

## Configuration

**File**: `config/scramble.php`

### Key Settings

```php
'api_path' => 'api',                    // Your API prefix
'api_domain' => null,                   // Use app domain
'export_path' => 'api.json',            // OpenAPI spec export path

'info' => [
    'version' => '1.0.0',               // API version
    'description' => '...',             // API description (Markdown supported)
],

'ui' => [
    'title' => 'HardwarePOS API Documentation',
    'theme' => 'system',                // Light/Dark/System
    'hide_try_it' => false,             // Enable "Try It" feature
    'layout' => 'responsive',           // Responsive layout
],

'servers' => [
    'Local Development' => 'http://localhost/api/v1',
    'Staging' => 'https://staging.hardwarepos.com/api/v1',
    'Production' => 'https://api.hardwarepos.com/api/v1',
],
```

---

## Accessing the Documentation

### 1. Start Your Laravel Server

```bash
cd d:\xampp\apache\bin\cloud-based-pos-backend
php artisan serve
```

### 2. Visit the Documentation URL

Open your browser and navigate to:

```
http://localhost:8000/docs/api
```

Or if using port 80:

```
http://localhost/docs/api
```

### 3. Explore the API

The documentation interface provides:

- **Navigation sidebar** - All endpoints organized by tag/group
- **Request details** - Method, URL, parameters, headers, body
- **Response schemas** - Expected response structure
- **Try It** - Interactive API testing right from the docs
- **Authentication** - Bearer token input for protected endpoints
- **Code samples** - Request examples in multiple languages

---

## Using "Try It" Feature

### Step 1: Authenticate

1. Click on any protected endpoint
2. Scroll to the **"Authorization"** section
3. Click **"Add Authorization"**
4. Select **"Bearer"** token type
5. Enter your API token (obtain via `/api/v1/auth/login`)

### Step 2: Test an Endpoint

1. Select an endpoint (e.g., `GET /api/v1/products`)
2. Fill in any required parameters
3. Click **"Send API Request"**
4. View the response in real-time

### Step 3: View Response

The response section shows:
- Status code (200, 201, 400, etc.)
- Response headers
- Response body (formatted JSON)
- Response time

---

## How Scramble Works

### Automatic Analysis

Scramble automatically scans your:

1. **Routes** (`routes/api.php`)
   - Extracts HTTP methods, paths, middleware
   - Groups routes by prefix

2. **Controllers**
   - Analyzes method signatures
   - Reads PHPDoc comments
   - Detects return types

3. **Form Requests**
   - Parses validation rules
   - Generates request schemas
   - Marks required fields

4. **API Resources**
   - Analyzes resource transformations
   - Generates response schemas

5. **Responses**
   - Detects response structure
   - Documents status codes

### Example: Well-Documented Endpoint

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    /**
     * Get all products with pagination and filters.
     *
     * Retrieve a paginated list of products. Supports filtering by category,
     * search query, and active status.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [...],
     *     "pagination": {...}
     *   }
     * }
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->q, fn($q) => $q->where('name', 'like', "%{$request->q}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ]);
    }

    /**
     * Create a new product.
     *
     * Add a new product to the inventory system.
     *
     * @param StoreProductRequest $request
     * @return JsonResponse
     *
     * @response 201 {
     *   "success": true,
     *   "data": {...},
     *   "message": "Product created successfully"
     * }
     */
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
            'message' => 'Product created successfully',
        ], 201);
    }
}
```

**Scramble will automatically:**
- Document the endpoints
- Extract parameter information from validation rules
- Generate request/response schemas
- Add descriptions from PHPDoc comments
- Support "Try It" functionality

---

## Improving Documentation

### 1. Add PHPDoc Comments

```php
/**
 * Update product stock level.
 *
 * Adjust the current stock quantity for a product. This creates a stock
 * adjustment record for audit trail purposes.
 *
 * @param Product $product The product to adjust
 * @param Request $request Stock adjustment details
 * @return JsonResponse
 *
 * @response 200 {
 *   "success": true,
 *   "data": {
 *     "product": {...},
 *     "adjustment": {...}
 *   },
 *   "message": "Stock adjusted successfully"
 * }
 *
 * @response 422 {
 *   "success": false,
 *   "message": "Validation failed",
 *   "errors": {
 *     "quantity": ["The quantity field is required"]
 *   }
 * }
 */
public function adjustStock(Product $product, Request $request)
{
    // Implementation
}
```

### 2. Use Form Requests with Clear Rules

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string|max:50',
            'category_id' => 'required|uuid|exists:categories,uuid',
            'unit_id' => 'required|integer|exists:units_of_measure,id',
            'cost_price' => 'required|integer|min:0',      // In centavos
            'retail_price' => 'required|integer|min:0',    // In centavos
            'current_stock' => 'required|integer|min:0',
            'reorder_point' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'cost_price.integer' => 'Cost price must be in centavos (e.g., 25000 for ₱250.00)',
            'retail_price.integer' => 'Retail price must be in centavos (e.g., 35000 for ₱350.00)',
        ];
    }
}
```

**Scramble will:**
- Mark fields as required/optional
- Add validation constraints
- Include custom error messages
- Generate accurate request schema

### 3. Use API Resources

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'cost_price' => $this->cost_price,           // Centavos
            'retail_price' => $this->retail_price,       // Centavos
            'cost_price_display' => '₱' . number_format($this->cost_price / 100, 2),
            'retail_price_display' => '₱' . number_format($this->retail_price / 100, 2),
            'current_stock' => $this->current_stock,
            'reorder_point' => $this->reorder_point,
            'is_low_stock' => $this->current_stock <= $this->reorder_point,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

**Scramble will:**
- Generate accurate response schemas
- Document nested relationships
- Show example response structure

---

## Organizing Endpoints

### Tagging Routes

Scramble automatically groups endpoints by route prefix, but you can customize:

```php
// In routes/api.php

Route::prefix('v1')->group(function () {
    // Authentication Group
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Products Group
    Route::prefix('products')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{product}', [ProductController::class, 'show']);
    });
});
```

This creates organized sections in the documentation sidebar.

---

## Exporting Documentation

### Export OpenAPI Specification

Generate a static OpenAPI JSON file:

```bash
php artisan scramble:export > public/api-docs.json
```

This creates a file that can be:
- Imported into Postman
- Used with Swagger UI
- Shared with API consumers
- Versioned in git

### Import to Postman

1. Export the OpenAPI spec:
   ```bash
   php artisan scramble:export > api-docs.json
   ```

2. Open Postman → Import
3. Select the `api-docs.json` file
4. Postman automatically creates a collection from the OpenAPI spec

---

## Advanced Configuration

### Custom Middlewares

If you want to restrict documentation access:

```php
// config/scramble.php

'middleware' => [
    'web',
    \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
    // Add custom middleware here
],
```

### Environment-Specific Access

```php
// In RestrictedDocsAccess middleware or custom middleware

public function handle($request, Closure $next)
{
    // Only allow in local and staging environments
    if (!app()->environment(['local', 'staging'])) {
        abort(404);
    }

    // Or require authentication
    if (!auth()->check() || !auth()->user()->hasRole('admin')) {
        abort(403);
    }

    return $next($request);
}
```

### Custom Servers

Update server URLs in `.env`:

```env
# .env

STAGING_API_URL=https://staging-api.hardwarepos.com/api/v1
PRODUCTION_API_URL=https://api.hardwarepos.com/api/v1
```

Then in `config/scramble.php`:

```php
'servers' => [
    'Local' => 'http://localhost/api/v1',
    'Staging' => env('STAGING_API_URL'),
    'Production' => env('PRODUCTION_API_URL'),
],
```

---

## Benefits of Scramble

### For Developers

✅ **Zero maintenance** - Documentation auto-updates with code changes
✅ **Interactive testing** - Test endpoints directly from docs
✅ **Type safety** - Catches documentation-code mismatches
✅ **Standards compliant** - OpenAPI 3.1.0 specification
✅ **Beautiful UI** - Professional, modern interface

### For API Consumers

✅ **Always up-to-date** - Documentation matches current code
✅ **Try before you buy** - Test API without writing code
✅ **Clear examples** - See request/response structures
✅ **Multiple environments** - Test against dev/staging/prod
✅ **Code generation** - Generate client SDKs from OpenAPI spec

### For Teams

✅ **Onboarding** - New developers can explore API easily
✅ **Communication** - Share documentation URL instead of writing docs
✅ **Quality** - Forces better code structure and validation
✅ **Standards** - Encourages consistent API design

---

## Comparison: Scramble vs Postman Collection

| Feature | Scramble Docs | Postman Collection |
|---------|---------------|-------------------|
| **Auto-generated** | ✅ Yes | ❌ Manual creation |
| **Always up-to-date** | ✅ Yes | ❌ Must update manually |
| **Interactive testing** | ✅ Yes | ✅ Yes |
| **OpenAPI spec** | ✅ Generates | ✅ Can import/export |
| **Share with team** | ✅ URL | ✅ Export file |
| **Environment switching** | ✅ Built-in | ✅ Built-in |
| **Code samples** | ✅ Multiple languages | ✅ Multiple languages |
| **Mobile friendly** | ✅ Yes | ❌ Desktop app only |
| **Public hosting** | ✅ Easy | ⚠️ Requires Postman cloud |

**Recommendation**: Use **both**!
- **Scramble** for developers and interactive exploration
- **Postman Collection** for comprehensive testing workflows

---

## Troubleshooting

### Documentation Not Showing

```bash
# Clear cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Verify Scramble is installed
composer show dedoc/scramble
```

### Endpoints Missing

Check that:
1. Routes are in `routes/api.php`
2. Routes start with the configured `api_path` (default: 'api')
3. Controllers are properly namespaced
4. No syntax errors in route files

### "Try It" Not Working

1. Ensure CORS is configured properly
2. Check that authentication token is valid
3. Verify API server is running
4. Check browser console for errors

### Styling Issues

```bash
# Re-publish Scramble assets
php artisan vendor:publish --tag=scramble-config --force
```

---

## Next Steps

### 1. Enhance Documentation

Add PHPDoc comments to all controller methods:
- Descriptions
- Parameter details
- Response examples
- Error responses

### 2. Create Form Requests

Convert inline validation to Form Request classes for better documentation.

### 3. Use API Resources

Transform all responses using API Resources for consistent schemas.

### 4. Test Documentation

Visit `/docs/api` and test all endpoints to ensure accuracy.

### 5. Share with Team

Provide the documentation URL to your team and API consumers.

---

## Resources

- **Scramble Documentation**: https://scramble.dedoc.co/
- **OpenAPI Specification**: https://swagger.io/specification/
- **Stoplight Elements**: https://stoplight.io/open-source/elements

---

## Summary

✅ Scramble configured and ready
✅ Documentation accessible at `/docs/api`
✅ Interactive "Try It" enabled
✅ Multiple server environments configured
✅ Auto-generates from your existing code
✅ Zero maintenance required

**Access your API documentation now:**

```
http://localhost:8000/docs/api
```

---

**Created**: February 2024
**Scramble Version**: 0.13.12
**OpenAPI Version**: 3.1.0
