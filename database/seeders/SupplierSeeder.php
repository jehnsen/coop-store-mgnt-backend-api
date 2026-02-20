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

        // ── Suppliers ─────────────────────────────────────────────────────────

        // 1. Seeds & Agri-Inputs — Regional PhilRice / DA-Accredited dealer
        $philrice = Supplier::create([
            'uuid'                  => Str::uuid(),
            'store_id'              => $store->id,
            'code'                  => 'SUP-001',
            'name'                  => 'Bernas Agri-Inputs Trading',
            'company_name'          => 'Bernas Agri-Inputs Trading Corp.',
            'tin'                   => '321-456-098-000',
            'email'                 => 'sales@bernasagri.com.ph',
            'phone'                 => '(044) 600-1234',
            'mobile'                => '0917-345-6789',
            'address'               => 'Maharlika Highway, Brgy. Cuyapo',
            'city'                  => 'Cuyapo',
            'province'              => 'Nueva Ecija',
            'postal_code'           => '3117',
            'contact_person'        => 'Danilo Batungbakal',
            'contact_person_phone'  => '0917-345-6789',
            'payment_terms_days'    => 30,
            'lead_time_days'        => 5,
            'notes'                 => 'Accredited PhilRice certified seed dealer. Minimum order: 50 kg per variety. Delivers to coop bodega.',
            'is_active'             => true,
        ]);

        // 2. Fertilizers — Fertiphil regional distributor
        $fertiphil = Supplier::create([
            'uuid'                  => Str::uuid(),
            'store_id'              => $store->id,
            'code'                  => 'SUP-002',
            'name'                  => 'Agriville Fertilizer & Chemical Supply',
            'company_name'          => 'Agriville Fertilizer & Chemical Supply Inc.',
            'tin'                   => '456-789-123-000',
            'email'                 => 'info@agrivillefertilizer.com',
            'phone'                 => '(044) 600-5678',
            'mobile'                => '0918-456-7890',
            'address'               => 'Maharlika Road, Brgy. San Roque',
            'city'                  => 'Cabanatuan City',
            'province'              => 'Nueva Ecija',
            'postal_code'           => '3100',
            'contact_person'        => 'Rosario Tagumpay',
            'contact_person_phone'  => '0918-456-7890',
            'payment_terms_days'    => 45,
            'lead_time_days'        => 7,
            'notes'                 => 'Authorized Fertiphil distributor for Central Luzon. Bulk orders qualify for free delivery within Nueva Ecija.',
            'is_active'             => true,
        ]);

        // 3. Pesticides & Veterinary — Bayer CropScience / FMC sub-dealer
        $bayer = Supplier::create([
            'uuid'                  => Str::uuid(),
            'store_id'              => $store->id,
            'code'                  => 'SUP-003',
            'name'                  => 'AgriChem Central Luzon Distributor',
            'company_name'          => 'AgriChem Central Luzon Dist. Inc.',
            'tin'                   => '567-890-234-000',
            'email'                 => 'agrichem.cl@yahoo.com',
            'phone'                 => '(044) 464-3456',
            'mobile'                => '0919-567-8901',
            'address'               => 'Diversion Road, Brgy. Abar 2nd',
            'city'                  => 'San Jose City',
            'province'              => 'Nueva Ecija',
            'postal_code'           => '3121',
            'contact_person'        => 'Bernardo Halili',
            'contact_person_phone'  => '0919-567-8901',
            'payment_terms_days'    => 30,
            'lead_time_days'        => 5,
            'notes'                 => 'Handles pesticides, herbicides, fungicides, and veterinary drugs. Licensed distributor per FPA. Cold-chain delivery for vaccines.',
            'is_active'             => true,
        ]);

        // 4. Animal Feeds — San Miguel Foods / Robina regional depot
        $smFoods = Supplier::create([
            'uuid'                  => Str::uuid(),
            'store_id'              => $store->id,
            'code'                  => 'SUP-004',
            'name'                  => 'Nueva Ecija Feeds & Livestock Supply',
            'company_name'          => 'Nueva Ecija Feeds & Livestock Supply Co.',
            'tin'                   => '678-901-345-000',
            'email'                 => 'nefeeds@gmail.com',
            'phone'                 => '(044) 940-7890',
            'mobile'                => '0920-678-9012',
            'address'               => 'Brgy. Rizal, San Leonardo',
            'city'                  => 'San Leonardo',
            'province'              => 'Nueva Ecija',
            'postal_code'           => '3109',
            'contact_person'        => 'Eduardo Palma',
            'contact_person_phone'  => '0920-678-9012',
            'payment_terms_days'    => 30,
            'lead_time_days'        => 3,
            'notes'                 => 'Authorized dealer: San Miguel B-MEG, Robina Farms, Vitarich. Minimum order: 5 sacks per SKU.',
            'is_active'             => true,
        ]);

        // 5. Grocery Distributor — NFA & commercial rice, canned goods, beverages
        $nfaDealer = Supplier::create([
            'uuid'                  => Str::uuid(),
            'store_id'              => $store->id,
            'code'                  => 'SUP-005',
            'name'                  => 'Salonga General Merchandise & Trading',
            'company_name'          => 'Salonga General Merchandise & Trading',
            'tin'                   => '789-012-456-000',
            'email'                 => 'salongagmt@gmail.com',
            'phone'                 => '(044) 940-2222',
            'mobile'                => '0921-789-0123',
            'address'               => 'Maharlika Highway, Brgy. Poblacion',
            'city'                  => 'San Isidro',
            'province'              => 'Nueva Ecija',
            'postal_code'           => '3112',
            'contact_person'        => 'Teresita Salonga',
            'contact_person_phone'  => '0921-789-0123',
            'payment_terms_days'    => 15,
            'lead_time_days'        => 2,
            'notes'                 => 'Local wholesaler for rice, canned goods, beverages, personal care, and general merchandise. Twice-weekly delivery.',
            'is_active'             => true,
        ]);

        // 6. Farm Tools & Packaging — Agri-hardware wholesaler
        $toolsSupplier = Supplier::create([
            'uuid'                  => Str::uuid(),
            'store_id'              => $store->id,
            'code'                  => 'SUP-006',
            'name'                  => 'Cabanatuan Agri-Hardware & Supply',
            'company_name'          => 'Cabanatuan Agri-Hardware & Supply Co.',
            'tin'                   => '890-123-567-000',
            'email'                 => 'cabagrisupply@gmail.com',
            'phone'                 => '(044) 464-5678',
            'mobile'                => '0922-890-1234',
            'address'               => 'Gen. Tinio Street, Brgy. Zulueta',
            'city'                  => 'Cabanatuan City',
            'province'              => 'Nueva Ecija',
            'postal_code'           => '3100',
            'contact_person'        => 'Marcelo Quiambao',
            'contact_person_phone'  => '0922-890-1234',
            'payment_terms_days'    => 30,
            'lead_time_days'        => 5,
            'notes'                 => 'Farm tools, sprayers, rubber boots, PP sacks, tarpaulins, baling twine, and fishing supplies.',
            'is_active'             => true,
        ]);

        // ── Link products to suppliers ────────────────────────────────────────

        // Bernas Agri — Seeds
        $seedProducts = Product::where('name', 'like', '%Rice Seed%')
            ->orWhere('name', 'like', '%Corn Seed%')
            ->orWhere('name', 'like', '%Vegetable Seeds%')
            ->orWhere('name', 'like', '%Seeds%')
            ->get();

        foreach ($seedProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id'            => $philrice->id,
                'product_id'             => $product->id,
                'supplier_sku'           => 'BRN-' . rand(1000, 9999),
                'supplier_price'         => (int) round($product->cost_price * 0.94),
                'lead_time_days'         => 5,
                'minimum_order_quantity' => 20,
                'is_preferred'           => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // Agriville — Fertilizers
        $fertProducts = Product::where('name', 'like', '%Fertilizer%')
            ->orWhere('name', 'like', '%Urea%')
            ->orWhere('name', 'like', '%Ammonium%')
            ->orWhere('name', 'like', '%Foliar%')
            ->orWhere('name', 'like', '%Compost%')
            ->get();

        foreach ($fertProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id'            => $fertiphil->id,
                'product_id'             => $product->id,
                'supplier_sku'           => 'AV-' . rand(1000, 9999),
                'supplier_price'         => (int) round($product->cost_price * 0.93),
                'lead_time_days'         => 7,
                'minimum_order_quantity' => 10,
                'is_preferred'           => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // AgriChem — Pesticides, Herbicides, Vet drugs
        $pestProducts = Product::where('name', 'like', '%Herbicide%')
            ->orWhere('name', 'like', '%Insecticide%')
            ->orWhere('name', 'like', '%Molluscicide%')
            ->orWhere('name', 'like', '%Fungicide%')
            ->orWhere('name', 'like', '%Butachlor%')
            ->orWhere('name', 'like', '%Glyphosate%')
            ->orWhere('name', 'like', '%Cypermethrin%')
            ->orWhere('name', 'like', '%Mancozeb%')
            ->orWhere('name', 'like', '%Metaldehyde%')
            ->orWhere('name', 'like', '%Vitamin B%')
            ->orWhere('name', 'like', '%Ivermectin%')
            ->get();

        foreach ($pestProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id'            => $bayer->id,
                'product_id'             => $product->id,
                'supplier_sku'           => 'AC-' . rand(1000, 9999),
                'supplier_price'         => (int) round($product->cost_price * 0.92),
                'lead_time_days'         => 5,
                'minimum_order_quantity' => 6,
                'is_preferred'           => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // NE Feeds — Animal feeds
        $feedProducts = Product::where('name', 'like', '%Feeds%')
            ->orWhere('name', 'like', '%Feed%')
            ->orWhere('name', 'like', '%Tilapia%')
            ->get();

        foreach ($feedProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id'            => $smFoods->id,
                'product_id'             => $product->id,
                'supplier_sku'           => 'NEF-' . rand(1000, 9999),
                'supplier_price'         => (int) round($product->cost_price * 0.93),
                'lead_time_days'         => 3,
                'minimum_order_quantity' => 5,
                'is_preferred'           => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // Salonga GMT — Grocery & consumer goods
        $groceryProducts = Product::where('name', 'like', '%Rice%')
            ->orWhere('name', 'like', '%Corn Grits%')
            ->orWhere('name', 'like', '%Monggo%')
            ->orWhere('name', 'like', '%Sugar%')
            ->orWhere('name', 'like', '%Salt%')
            ->orWhere('name', 'like', '%Soy Sauce%')
            ->orWhere('name', 'like', '%Vinegar%')
            ->orWhere('name', 'like', '%Cooking Oil%')
            ->orWhere('name', 'like', '%Sardines%')
            ->orWhere('name', 'like', '%Tuna%')
            ->orWhere('name', 'like', '%Corned Beef%')
            ->orWhere('name', 'like', '%Noodles%')
            ->orWhere('name', 'like', '%Coffee%')
            ->orWhere('name', 'like', '%Coca-Cola%')
            ->orWhere('name', 'like', '%Water%')
            ->orWhere('name', 'like', '%Tang%')
            ->orWhere('name', 'like', '%Soap%')
            ->orWhere('name', 'like', '%Shampoo%')
            ->orWhere('name', 'like', '%Toothpaste%')
            ->orWhere('name', 'like', '%Detergent%')
            ->orWhere('name', 'like', '%Diaper%')
            ->orWhere('name', 'like', '%Milk%')
            ->orWhere('name', 'like', '%Crackers%')
            ->orWhere('name', 'like', '%Chips%')
            ->orWhere('name', 'like', '%Bread%')
            ->orWhere('name', 'like', '%Batteries%')
            ->orWhere('name', 'like', '%Candle%')
            ->orWhere('name', 'like', '%Matchbox%')
            ->orWhere('name', 'like', '%Pad Paper%')
            ->get();

        foreach ($groceryProducts as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id'            => $nfaDealer->id,
                'product_id'             => $product->id,
                'supplier_sku'           => 'SGM-' . rand(1000, 9999),
                'supplier_price'         => (int) round($product->cost_price * 0.95),
                'lead_time_days'         => 2,
                'minimum_order_quantity' => 12,
                'is_preferred'           => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // Cabanatuan Agri-Hardware — Farm tools, sprayers, packaging, fishing
        $toolsAndPackaging = Product::where('name', 'like', '%Sprayer%')
            ->orWhere('name', 'like', '%Bolo%')
            ->orWhere('name', 'like', '%Hoe%')
            ->orWhere('name', 'like', '%Sickle%')
            ->orWhere('name', 'like', '%Rubber Boots%')
            ->orWhere('name', 'like', '%PP%')
            ->orWhere('name', 'like', '%Sack%')
            ->orWhere('name', 'like', '%Tarpaulin%')
            ->orWhere('name', 'like', '%Twine%')
            ->orWhere('name', 'like', '%Fishing%')
            ->orWhere('name', 'like', '%Nylon%')
            ->orWhere('name', 'like', '%Monofilament%')
            ->get();

        foreach ($toolsAndPackaging as $product) {
            DB::table('supplier_products')->insertOrIgnore([
                'supplier_id'            => $toolsSupplier->id,
                'product_id'             => $product->id,
                'supplier_sku'           => 'CAH-' . rand(1000, 9999),
                'supplier_price'         => (int) round($product->cost_price * 0.92),
                'lead_time_days'         => 5,
                'minimum_order_quantity' => 5,
                'is_preferred'           => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }
}
