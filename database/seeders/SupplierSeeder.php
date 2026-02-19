<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();

        // Create suppliers
        $holcim = Supplier::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'code' => 'SUP-001',
            'name' => 'Holcim Philippines Inc.',
            'company_name' => 'Holcim Philippines Inc.',
            'tin' => '000-123-456-000',
            'email' => 'sales@holcim.ph',
            'phone' => '(02) 8849-3600',
            'mobile' => '0917-567-8901',
            'address' => 'Holcim Building, Rizal Drive, Bonifacio Global City',
            'city' => 'Taguig City',
            'province' => 'Metro Manila',
            'postal_code' => '1634',
            'contact_person' => 'Romeo Santiago',
            'contact_person_phone' => '0917-567-8901',
            'payment_terms_days' => 45,
            'lead_time_days' => 7,
            'notes' => 'Cement products supplier. Minimum order 50 bags.',
            'is_active' => true,
        ]);

        $steelasia = Supplier::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'code' => 'SUP-002',
            'name' => 'SteelAsia Manufacturing Corp.',
            'company_name' => 'SteelAsia Manufacturing Corporation',
            'tin' => '000-234-567-000',
            'email' => 'inquiry@steelasia.com',
            'phone' => '(02) 8234-5678',
            'mobile' => '0918-678-9012',
            'address' => 'SteelAsia Compound, Calamba',
            'city' => 'Calamba City',
            'province' => 'Laguna',
            'postal_code' => '4027',
            'contact_person' => 'Angela Cruz',
            'contact_person_phone' => '0918-678-9012',
            'payment_terms_days' => 60,
            'lead_time_days' => 14,
            'notes' => 'Steel and rebar supplier. Bulk orders get 5% additional discount.',
            'is_active' => true,
        ]);

        $boysen = Supplier::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'code' => 'SUP-003',
            'name' => 'Boysen Paints Philippines',
            'company_name' => 'Pacific Paint (Boysen) Philippines, Inc.',
            'tin' => '000-345-678-000',
            'email' => 'customercare@boysen.com.ph',
            'phone' => '(02) 8363-9000',
            'mobile' => '0919-789-0123',
            'address' => 'Km. 14 East Service Road, Bicutan',
            'city' => 'Taguig City',
            'province' => 'Metro Manila',
            'postal_code' => '1630',
            'contact_person' => 'Teresa Morales',
            'contact_person_phone' => '0919-789-0123',
            'payment_terms_days' => 30,
            'lead_time_days' => 5,
            'notes' => 'Paint and coatings supplier. Free delivery for orders above ₱20,000.',
            'is_active' => true,
        ]);

        $pacific = Supplier::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'code' => 'SUP-004',
            'name' => 'Pacific Pipes & Fittings',
            'company_name' => 'Pacific Pipes & Fittings Corporation',
            'tin' => '000-456-789-000',
            'email' => 'sales@pacificpipes.com.ph',
            'phone' => '(02) 8456-7890',
            'mobile' => '0920-890-1234',
            'address' => '123 EDSA, Mandaluyong',
            'city' => 'Mandaluyong City',
            'province' => 'Metro Manila',
            'postal_code' => '1550',
            'contact_person' => 'Ricardo Dela Cruz',
            'contact_person_phone' => '0920-890-1234',
            'payment_terms_days' => 30,
            'lead_time_days' => 7,
            'notes' => 'PVC pipes and plumbing supplies.',
            'is_active' => true,
        ]);

        $abc = Supplier::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'code' => 'SUP-005',
            'name' => 'ABC General Hardware Supply',
            'company_name' => 'ABC General Hardware Supply Inc.',
            'tin' => '000-567-890-000',
            'email' => 'info@abchardware.com.ph',
            'phone' => '(02) 8567-8901',
            'mobile' => '0921-901-2345',
            'address' => '456 Aurora Boulevard, Cubao',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1109',
            'contact_person' => 'Benjamin Tan',
            'contact_person_phone' => '0921-901-2345',
            'payment_terms_days' => 30,
            'lead_time_days' => 5,
            'notes' => 'General hardware, tools, electrical, and miscellaneous items.',
            'is_active' => true,
        ]);

        // Link products to suppliers via supplier_products pivot table

        // Holcim - Cement products
        $cementProducts = Product::whereIn('name', [
            'Holcim Portland Cement',
            'Eagle Portland Cement',
            'Republic Portland Cement'
        ])->get();

        foreach ($cementProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id' => $holcim->id,
                'product_id' => $product->id,
                'supplier_sku' => 'HOL-' . rand(1000, 9999),
                'supplier_price' => $product->cost_price - rand(500, 1000), // Slightly lower than cost
                'lead_time_days' => 7,
                'minimum_order_quantity' => 50,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // SteelAsia - Steel and rebar products
        $steelProducts = Product::where('name', 'like', '%Deformed Bar%')
            ->orWhere('name', 'like', '%Steel Plate%')
            ->orWhere('name', 'like', '%Angle Bar%')
            ->get();

        foreach ($steelProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id' => $steelasia->id,
                'product_id' => $product->id,
                'supplier_sku' => 'SA-' . rand(1000, 9999),
                'supplier_price' => $product->cost_price - rand(500, 2000),
                'lead_time_days' => 14,
                'minimum_order_quantity' => $product->size === '10mm x 6m' ? 50 : 20,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Boysen - Paint products
        $paintProducts = Product::where('category_id', Product::where('name', 'Boysen Latex White')->first()->category_id)
            ->get();

        foreach ($paintProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id' => $boysen->id,
                'product_id' => $product->id,
                'supplier_sku' => 'BOY-' . rand(1000, 9999),
                'supplier_price' => $product->cost_price - rand(300, 800),
                'lead_time_days' => 5,
                'minimum_order_quantity' => 10,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Pacific - Plumbing products
        $plumbingProducts = Product::where('name', 'like', '%PVC%')
            ->orWhere('name', 'like', '%Ball Valve%')
            ->orWhere('name', 'like', '%Water Tank%')
            ->get();

        foreach ($plumbingProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id' => $pacific->id,
                'product_id' => $product->id,
                'supplier_sku' => 'PAC-' . rand(1000, 9999),
                'supplier_price' => $product->cost_price - rand(200, 1000),
                'lead_time_days' => 7,
                'minimum_order_quantity' => $product->name === 'Water Tank 500L' ? 3 : 30,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ABC - Miscellaneous (electrical, nails, safety, general hardware)
        $miscProducts = Product::where('name', 'like', '%Wire%')
            ->orWhere('name', 'like', '%Nail%')
            ->orWhere('name', 'like', '%Screw%')
            ->orWhere('name', 'like', '%Safety%')
            ->orWhere('name', 'like', '%Circuit Breaker%')
            ->orWhere('name', 'like', '%Outlet%')
            ->orWhere('name', 'like', '%Switch%')
            ->orWhere('name', 'like', '%Conduit%')
            ->orWhere('name', 'like', '%Helmet%')
            ->orWhere('name', 'like', '%Gloves%')
            ->orWhere('name', 'like', '%Goggles%')
            ->get();

        foreach ($miscProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id' => $abc->id,
                'product_id' => $product->id,
                'supplier_sku' => 'ABC-' . rand(1000, 9999),
                'supplier_price' => $product->cost_price - rand(100, 500),
                'lead_time_days' => 5,
                'minimum_order_quantity' => 20,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ABC also supplies lumber, roofing, blocks, sand & gravel
        $additionalProducts = Product::where('name', 'like', '%Lumber%')
            ->orWhere('name', 'like', '%Plywood%')
            ->orWhere('name', 'like', '%Lawanit%')
            ->orWhere('name', 'like', '%Roofing%')
            ->orWhere('name', 'like', '%Ceiling%')
            ->orWhere('name', 'like', '%CHB%')
            ->orWhere('name', 'like', '%Sand%')
            ->orWhere('name', 'like', '%Gravel%')
            ->get();

        foreach ($additionalProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id' => $abc->id,
                'product_id' => $product->id,
                'supplier_sku' => 'ABC-' . rand(1000, 9999),
                'supplier_price' => $product->cost_price - rand(200, 1500),
                'lead_time_days' => 7,
                'minimum_order_quantity' => $product->name === 'River Sand' || $product->name === 'Washed Sand' || $product->name === 'Gravel 3/4"' ? 2 : 10,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
