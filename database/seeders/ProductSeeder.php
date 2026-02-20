<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Category;
use App\Models\UnitOfMeasure;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();

        // ── Categories ────────────────────────────────────────────────────────
        $catSeeds       = Category::where('slug', 'seeds-planting-materials')->first();
        $catFert        = Category::where('slug', 'fertilizers')->first();
        $catPest        = Category::where('slug', 'pesticides-herbicides')->first();
        $catTools       = Category::where('slug', 'farm-tools-equipment')->first();
        $catFeeds       = Category::where('slug', 'animal-feeds-veterinary')->first();
        $catFishing     = Category::where('slug', 'fishing-supplies')->first();
        $catPacking     = Category::where('slug', 'farm-packaging-storage')->first();
        $catRice        = Category::where('slug', 'rice-grains')->first();
        $catCooking     = Category::where('slug', 'cooking-essentials')->first();
        $catCanned      = Category::where('slug', 'canned-processed-goods')->first();
        $catBev         = Category::where('slug', 'beverages')->first();
        $catHygiene     = Category::where('slug', 'personal-care-hygiene')->first();
        $catBaby        = Category::where('slug', 'baby-infant-needs')->first();
        $catSnacks      = Category::where('slug', 'snacks-confectionery')->first();
        $catGeneral     = Category::where('slug', 'general-merchandise')->first();

        // ── Units ─────────────────────────────────────────────────────────────
        $kg      = UnitOfMeasure::where('abbreviation', 'kg')->first();
        $g       = UnitOfMeasure::where('abbreviation', 'g')->first();
        $sack    = UnitOfMeasure::where('abbreviation', 'sack')->first();
        $bag     = UnitOfMeasure::where('abbreviation', 'bag')->first();
        $liter   = UnitOfMeasure::where('abbreviation', 'L')->first();
        $mL      = UnitOfMeasure::where('abbreviation', 'mL')->first();
        $gallon  = UnitOfMeasure::where('abbreviation', 'gal')->first();
        $pcs     = UnitOfMeasure::where('abbreviation', 'pcs')->first();
        $pack    = UnitOfMeasure::where('abbreviation', 'pack')->first();
        $box     = UnitOfMeasure::where('abbreviation', 'box')->first();
        $bundle  = UnitOfMeasure::where('abbreviation', 'bundle')->first();
        $roll    = UnitOfMeasure::where('abbreviation', 'roll')->first();
        $doz     = UnitOfMeasure::where('abbreviation', 'doz')->first();
        $set     = UnitOfMeasure::where('abbreviation', 'set')->first();
        $meter   = UnitOfMeasure::where('abbreviation', 'm')->first();
        $can     = UnitOfMeasure::where('abbreviation', 'can')->first();
        $tab     = UnitOfMeasure::where('abbreviation', 'tab')->first();
        $amp     = UnitOfMeasure::where('abbreviation', 'amp')->first();
        $sachet  = UnitOfMeasure::where('abbreviation', 'sachet')->first();

        // ── Products ──────────────────────────────────────────────────────────
        // cost_price / retail_price in CENTAVOS
        // wholesale_price ≈ 95% of retail; contractor_price retained for member price (~90%)
        $products = [

            // ═══════════════════════════════════════════
            // SEEDS & PLANTING MATERIALS
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catSeeds->id,
                'unit_id'            => $kg->id,
                'name'               => 'NSIC Rc 222 Certified Inbred Rice Seed',
                'brand'              => 'PhilRice',
                'size'               => '1 kg',
                'cost_price'         => 7600,   // ₱76
                'retail_price'       => 9500,   // ₱95
                'wholesale_price'    => 9025,   // ₱90.25
                'contractor_price'   => 8550,   // ₱85.50 (member price)
                'current_stock'      => 850,
                'reorder_point'      => 200,
                'minimum_order_qty'  => 50,
            ],
            [
                'category_id'        => $catSeeds->id,
                'unit_id'            => $kg->id,
                'name'               => 'Mestizo 1 (MS1) Hybrid Rice Seed',
                'brand'              => 'SL Agritech',
                'size'               => '1 kg',
                'cost_price'         => 21000,  // ₱210
                'retail_price'       => 26000,  // ₱260
                'wholesale_price'    => 24700,  // ₱247
                'contractor_price'   => 23400,  // ₱234
                'current_stock'      => 420,
                'reorder_point'      => 100,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catSeeds->id,
                'unit_id'            => $kg->id,
                'name'               => 'Open-Pollinated Corn Seed (Yellow)',
                'brand'              => 'DA-BPI',
                'size'               => '1 kg',
                'cost_price'         => 9200,   // ₱92
                'retail_price'       => 11500,  // ₱115
                'wholesale_price'    => 10925,  // ₱109.25
                'contractor_price'   => 10350,  // ₱103.50
                'current_stock'      => 380,
                'reorder_point'      => 80,
                'minimum_order_qty'  => 20,
            ],
            [
                'category_id'        => $catSeeds->id,
                'unit_id'            => $pack->id,
                'name'               => 'Pechay (Bok Choy) Vegetable Seeds',
                'brand'              => 'East-West Seed',
                'size'               => '10g pack',
                'cost_price'         => 3600,   // ₱36
                'retail_price'       => 4500,   // ₱45
                'wholesale_price'    => 4275,   // ₱42.75
                'contractor_price'   => 4050,   // ₱40.50
                'current_stock'      => 240,
                'reorder_point'      => 50,
                'minimum_order_qty'  => 20,
            ],
            [
                'category_id'        => $catSeeds->id,
                'unit_id'            => $pack->id,
                'name'               => 'Ampalaya (Bitter Gourd) Seeds',
                'brand'              => 'East-West Seed',
                'size'               => '5g pack',
                'cost_price'         => 4800,   // ₱48
                'retail_price'       => 6000,   // ₱60
                'wholesale_price'    => 5700,   // ₱57
                'contractor_price'   => 5400,   // ₱54
                'current_stock'      => 180,
                'reorder_point'      => 40,
                'minimum_order_qty'  => 20,
            ],

            // ═══════════════════════════════════════════
            // FERTILIZERS
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catFert->id,
                'unit_id'            => $sack->id,
                'name'               => 'Urea (46-0-0) Fertilizer',
                'brand'              => 'Fertiphil',
                'size'               => '50 kg sack',
                'cost_price'         => 148000, // ₱1,480
                'retail_price'       => 175000, // ₱1,750
                'wholesale_price'    => 166250, // ₱1,662.50
                'contractor_price'   => 157500, // ₱1,575
                'current_stock'      => 320,
                'reorder_point'      => 60,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catFert->id,
                'unit_id'            => $sack->id,
                'name'               => 'Complete Fertilizer (14-14-14)',
                'brand'              => 'Fertiphil',
                'size'               => '50 kg sack',
                'cost_price'         => 168000, // ₱1,680
                'retail_price'       => 198000, // ₱1,980
                'wholesale_price'    => 188100, // ₱1,881
                'contractor_price'   => 178200, // ₱1,782
                'current_stock'      => 280,
                'reorder_point'      => 50,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catFert->id,
                'unit_id'            => $sack->id,
                'name'               => 'Ammonium Sulfate (21-0-0)',
                'brand'              => 'Fertiphil',
                'size'               => '50 kg sack',
                'cost_price'         => 108000, // ₱1,080
                'retail_price'       => 127000, // ₱1,270
                'wholesale_price'    => 120650, // ₱1,206.50
                'contractor_price'   => 114300, // ₱1,143
                'current_stock'      => 220,
                'reorder_point'      => 40,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catFert->id,
                'unit_id'            => $liter->id,
                'name'               => 'Foliar Fertilizer (Bayfolan)',
                'brand'              => 'Bayer CropScience',
                'size'               => '1 L',
                'cost_price'         => 34000,  // ₱340
                'retail_price'       => 42500,  // ₱425
                'wholesale_price'    => 40375,  // ₱403.75
                'contractor_price'   => 38250,  // ₱382.50
                'current_stock'      => 145,
                'reorder_point'      => 30,
                'minimum_order_qty'  => 6,
            ],
            [
                'category_id'        => $catFert->id,
                'unit_id'            => $sack->id,
                'name'               => 'Organic Compost (Vermicast)',
                'brand'              => 'Local Organic',
                'size'               => '30 kg sack',
                'cost_price'         => 24000,  // ₱240
                'retail_price'       => 30000,  // ₱300
                'wholesale_price'    => 28500,  // ₱285
                'contractor_price'   => 27000,  // ₱270
                'current_stock'      => 180,
                'reorder_point'      => 30,
                'minimum_order_qty'  => 5,
            ],

            // ═══════════════════════════════════════════
            // PESTICIDES & HERBICIDES
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catPest->id,
                'unit_id'            => $liter->id,
                'name'               => 'Butachlor 60% EC (Machete) Herbicide',
                'brand'              => 'Bayer CropScience',
                'size'               => '1 L',
                'cost_price'         => 54000,  // ₱540
                'retail_price'       => 67500,  // ₱675
                'wholesale_price'    => 64125,  // ₱641.25
                'contractor_price'   => 60750,  // ₱607.50
                'current_stock'      => 110,
                'reorder_point'      => 25,
                'minimum_order_qty'  => 6,
            ],
            [
                'category_id'        => $catPest->id,
                'unit_id'            => $liter->id,
                'name'               => 'Glyphosate 480 g/L (Roundup) Herbicide',
                'brand'              => 'Monsanto',
                'size'               => '1 L',
                'cost_price'         => 28000,  // ₱280
                'retail_price'       => 35000,  // ₱350
                'wholesale_price'    => 33250,  // ₱332.50
                'contractor_price'   => 31500,  // ₱315
                'current_stock'      => 140,
                'reorder_point'      => 30,
                'minimum_order_qty'  => 6,
            ],
            [
                'category_id'        => $catPest->id,
                'unit_id'            => $liter->id,
                'name'               => 'Cypermethrin 5% EC Insecticide',
                'brand'              => 'FMC',
                'size'               => '500 mL',
                'cost_price'         => 19600,  // ₱196
                'retail_price'       => 24500,  // ₱245
                'wholesale_price'    => 23275,  // ₱232.75
                'contractor_price'   => 22050,  // ₱220.50
                'current_stock'      => 130,
                'reorder_point'      => 25,
                'minimum_order_qty'  => 6,
            ],
            [
                'category_id'        => $catPest->id,
                'unit_id'            => $kg->id,
                'name'               => 'Metaldehyde 6% (Snailmate) Molluscicide',
                'brand'              => 'Bayer',
                'size'               => '500 g',
                'cost_price'         => 11200,  // ₱112
                'retail_price'       => 14000,  // ₱140
                'wholesale_price'    => 13300,  // ₱133
                'contractor_price'   => 12600,  // ₱126
                'current_stock'      => 210,
                'reorder_point'      => 40,
                'minimum_order_qty'  => 12,
            ],
            [
                'category_id'        => $catPest->id,
                'unit_id'            => $kg->id,
                'name'               => 'Mancozeb 80% WP Fungicide',
                'brand'              => 'Indofil',
                'size'               => '1 kg',
                'cost_price'         => 25600,  // ₱256
                'retail_price'       => 32000,  // ₱320
                'wholesale_price'    => 30400,  // ₱304
                'contractor_price'   => 28800,  // ₱288
                'current_stock'      => 95,
                'reorder_point'      => 20,
                'minimum_order_qty'  => 6,
            ],

            // ═══════════════════════════════════════════
            // FARM TOOLS & EQUIPMENT
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catTools->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Hand Sprayer (Knapsack) 16L',
                'brand'              => 'Solo',
                'size'               => '16 Liters',
                'cost_price'         => 148000, // ₱1,480
                'retail_price'       => 185000, // ₱1,850
                'wholesale_price'    => 175750, // ₱1,757.50
                'contractor_price'   => 166500, // ₱1,665
                'current_stock'      => 45,
                'reorder_point'      => 8,
                'minimum_order_qty'  => 3,
            ],
            [
                'category_id'        => $catTools->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Kaingin Bolo (Itak) Heavy Duty',
                'brand'              => 'Batangas Forge',
                'size'               => '18 inch',
                'cost_price'         => 22400,  // ₱224
                'retail_price'       => 28000,  // ₱280
                'wholesale_price'    => 26600,  // ₱266
                'contractor_price'   => 25200,  // ₱252
                'current_stock'      => 90,
                'reorder_point'      => 15,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catTools->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Garden Hoe (Asarol)',
                'brand'              => 'Generic',
                'size'               => 'Standard',
                'cost_price'         => 18400,  // ₱184
                'retail_price'       => 23000,  // ₱230
                'wholesale_price'    => 21850,  // ₱218.50
                'contractor_price'   => 20700,  // ₱207
                'current_stock'      => 75,
                'reorder_point'      => 15,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catTools->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Sickle / Lilik (Harvesting Blade)',
                'brand'              => 'Generic',
                'size'               => 'Standard',
                'cost_price'         => 6400,   // ₱64
                'retail_price'       => 8000,   // ₱80
                'wholesale_price'    => 7600,   // ₱76
                'contractor_price'   => 7200,   // ₱72
                'current_stock'      => 130,
                'reorder_point'      => 25,
                'minimum_order_qty'  => 20,
            ],
            [
                'category_id'        => $catTools->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Rubber Boots (Bota de Goma) Farmer Grade',
                'brand'              => 'Dunlop',
                'size'               => 'Size 9',
                'cost_price'         => 44000,  // ₱440
                'retail_price'       => 55000,  // ₱550
                'wholesale_price'    => 52250,  // ₱522.50
                'contractor_price'   => 49500,  // ₱495
                'current_stock'      => 60,
                'reorder_point'      => 12,
                'minimum_order_qty'  => 6,
            ],

            // ═══════════════════════════════════════════
            // ANIMAL FEEDS & VETERINARY
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catFeeds->id,
                'unit_id'            => $sack->id,
                'name'               => 'Broiler Starter Feeds (0-28 days)',
                'brand'              => 'San Miguel Foods',
                'size'               => '50 kg sack',
                'cost_price'         => 192000, // ₱1,920
                'retail_price'       => 240000, // ₱2,400
                'wholesale_price'    => 228000, // ₱2,280
                'contractor_price'   => 216000, // ₱2,160
                'current_stock'      => 90,
                'reorder_point'      => 15,
                'minimum_order_qty'  => 5,
            ],
            [
                'category_id'        => $catFeeds->id,
                'unit_id'            => $sack->id,
                'name'               => 'Hog Grower Feeds',
                'brand'              => 'Robina Farms',
                'size'               => '50 kg sack',
                'cost_price'         => 196000, // ₱1,960
                'retail_price'       => 245000, // ₱2,450
                'wholesale_price'    => 232750, // ₱2,327.50
                'contractor_price'   => 220500, // ₱2,205
                'current_stock'      => 70,
                'reorder_point'      => 12,
                'minimum_order_qty'  => 5,
            ],
            [
                'category_id'        => $catFeeds->id,
                'unit_id'            => $sack->id,
                'name'               => 'Carabao / Cattle Concentrate Feed',
                'brand'              => 'San Miguel Foods',
                'size'               => '40 kg sack',
                'cost_price'         => 128000, // ₱1,280
                'retail_price'       => 160000, // ₱1,600
                'wholesale_price'    => 152000, // ₱1,520
                'contractor_price'   => 144000, // ₱1,440
                'current_stock'      => 50,
                'reorder_point'      => 10,
                'minimum_order_qty'  => 5,
            ],
            [
                'category_id'        => $catFeeds->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Vitamin B Complex + Iron Injectable (10 mL)',
                'brand'              => 'Vetplus',
                'size'               => '10 mL ampule',
                'cost_price'         => 7200,   // ₱72
                'retail_price'       => 9000,   // ₱90
                'wholesale_price'    => 8550,   // ₱85.50
                'contractor_price'   => 8100,   // ₱81
                'current_stock'      => 120,
                'reorder_point'      => 24,
                'minimum_order_qty'  => 12,
            ],
            [
                'category_id'        => $catFeeds->id,
                'unit_id'            => $tab->id,
                'name'               => 'Ivermectin Dewormer Tablet (Livestock)',
                'brand'              => 'Merial',
                'size'               => '10 tablets/blister',
                'cost_price'         => 8000,   // ₱80
                'retail_price'       => 10000,  // ₱100
                'wholesale_price'    => 9500,   // ₱95
                'contractor_price'   => 9000,   // ₱90
                'current_stock'      => 180,
                'reorder_point'      => 40,
                'minimum_order_qty'  => 20,
            ],

            // ═══════════════════════════════════════════
            // FISHING SUPPLIES
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catFishing->id,
                'unit_id'            => $sack->id,
                'name'               => 'Tilapia Fingerling Feeds (Floater)',
                'brand'              => 'Vitarich',
                'size'               => '25 kg sack',
                'cost_price'         => 104000, // ₱1,040
                'retail_price'       => 130000, // ₱1,300
                'wholesale_price'    => 123500, // ₱1,235
                'contractor_price'   => 117000, // ₱1,170
                'current_stock'      => 55,
                'reorder_point'      => 10,
                'minimum_order_qty'  => 3,
            ],
            [
                'category_id'        => $catFishing->id,
                'unit_id'            => $roll->id,
                'name'               => 'Nylon Monofilament Fishing Line #40',
                'brand'              => 'Generic',
                'size'               => '100m roll',
                'cost_price'         => 5600,   // ₱56
                'retail_price'       => 7000,   // ₱70
                'wholesale_price'    => 6650,   // ₱66.50
                'contractor_price'   => 6300,   // ₱63
                'current_stock'      => 200,
                'reorder_point'      => 40,
                'minimum_order_qty'  => 20,
            ],

            // ═══════════════════════════════════════════
            // FARM PACKAGING & STORAGE
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catPacking->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Polypropylene (PP) Rice Sack 50kg',
                'brand'              => 'Generic',
                'size'               => '50 kg capacity',
                'cost_price'         => 1200,   // ₱12
                'retail_price'       => 1500,   // ₱15
                'wholesale_price'    => 1425,   // ₱14.25
                'contractor_price'   => 1350,   // ₱13.50
                'current_stock'      => 2500,
                'reorder_point'      => 500,
                'minimum_order_qty'  => 100,
            ],
            [
                'category_id'        => $catPacking->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Agricultural Tarpaulin (6x8 ft)',
                'brand'              => 'Generic',
                'size'               => '6ft x 8ft',
                'cost_price'         => 18400,  // ₱184
                'retail_price'       => 23000,  // ₱230
                'wholesale_price'    => 21850,  // ₱218.50
                'contractor_price'   => 20700,  // ₱207
                'current_stock'      => 80,
                'reorder_point'      => 15,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catPacking->id,
                'unit_id'            => $roll->id,
                'name'               => 'Baling Twine (Abaca/Plastic)',
                'brand'              => 'Generic',
                'size'               => '200m roll',
                'cost_price'         => 8800,   // ₱88
                'retail_price'       => 11000,  // ₱110
                'wholesale_price'    => 10450,  // ₱104.50
                'contractor_price'   => 9900,   // ₱99
                'current_stock'      => 120,
                'reorder_point'      => 25,
                'minimum_order_qty'  => 10,
            ],

            // ═══════════════════════════════════════════
            // RICE & GRAINS
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catRice->id,
                'unit_id'            => $kg->id,
                'name'               => 'Well-milled Rice (Sinandomeng)',
                'brand'              => 'Local',
                'size'               => 'per kg',
                'cost_price'         => 4500,   // ₱45
                'retail_price'       => 5400,   // ₱54
                'wholesale_price'    => 5130,   // ₱51.30
                'contractor_price'   => 4860,   // ₱48.60 (member price)
                'current_stock'      => 3000,
                'reorder_point'      => 500,
                'minimum_order_qty'  => 50,
            ],
            [
                'category_id'        => $catRice->id,
                'unit_id'            => $kg->id,
                'name'               => 'Dinorado Premium Rice',
                'brand'              => 'Local',
                'size'               => 'per kg',
                'cost_price'         => 5200,   // ₱52
                'retail_price'       => 6300,   // ₱63
                'wholesale_price'    => 5985,   // ₱59.85
                'contractor_price'   => 5670,   // ₱56.70
                'current_stock'      => 1500,
                'reorder_point'      => 300,
                'minimum_order_qty'  => 25,
            ],
            [
                'category_id'        => $catRice->id,
                'unit_id'            => $kg->id,
                'name'               => 'Yellow Corn Grits',
                'brand'              => 'Local',
                'size'               => 'per kg',
                'cost_price'         => 2400,   // ₱24
                'retail_price'       => 3000,   // ₱30
                'wholesale_price'    => 2850,   // ₱28.50
                'contractor_price'   => 2700,   // ₱27
                'current_stock'      => 800,
                'reorder_point'      => 150,
                'minimum_order_qty'  => 25,
            ],
            [
                'category_id'        => $catRice->id,
                'unit_id'            => $kg->id,
                'name'               => 'Monggo (Mung Beans)',
                'brand'              => 'Local',
                'size'               => 'per kg',
                'cost_price'         => 9600,   // ₱96
                'retail_price'       => 12000,  // ₱120
                'wholesale_price'    => 11400,  // ₱114
                'contractor_price'   => 10800,  // ₱108
                'current_stock'      => 250,
                'reorder_point'      => 50,
                'minimum_order_qty'  => 10,
            ],

            // ═══════════════════════════════════════════
            // COOKING ESSENTIALS
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catCooking->id,
                'unit_id'            => $liter->id,
                'name'               => 'Minola Coconut Cooking Oil',
                'brand'              => 'Minola',
                'size'               => '1 L',
                'cost_price'         => 9200,   // ₱92
                'retail_price'       => 11500,  // ₱115
                'wholesale_price'    => 10925,  // ₱109.25
                'contractor_price'   => 10350,  // ₱103.50
                'current_stock'      => 350,
                'reorder_point'      => 60,
                'minimum_order_qty'  => 12,
            ],
            [
                'category_id'        => $catCooking->id,
                'unit_id'            => $kg->id,
                'name'               => 'Refined White Sugar (Granulated)',
                'brand'              => 'Crystalline',
                'size'               => 'per kg',
                'cost_price'         => 7200,   // ₱72
                'retail_price'       => 9000,   // ₱90
                'wholesale_price'    => 8550,   // ₱85.50
                'contractor_price'   => 8100,   // ₱81
                'current_stock'      => 500,
                'reorder_point'      => 80,
                'minimum_order_qty'  => 10,
            ],
            [
                'category_id'        => $catCooking->id,
                'unit_id'            => $kg->id,
                'name'               => 'Iodized Salt (Asin)',
                'brand'              => 'Silver Swan',
                'size'               => 'per kg',
                'cost_price'         => 1200,   // ₱12
                'retail_price'       => 1500,   // ₱15
                'wholesale_price'    => 1425,   // ₱14.25
                'contractor_price'   => 1350,   // ₱13.50
                'current_stock'      => 600,
                'reorder_point'      => 100,
                'minimum_order_qty'  => 20,
            ],
            [
                'category_id'        => $catCooking->id,
                'unit_id'            => $mL->id,
                'name'               => 'Silver Swan Soy Sauce (Toyo)',
                'brand'              => 'Silver Swan',
                'size'               => '350 mL',
                'cost_price'         => 2400,   // ₱24
                'retail_price'       => 3000,   // ₱30
                'wholesale_price'    => 2850,   // ₱28.50
                'contractor_price'   => 2700,   // ₱27
                'current_stock'      => 360,
                'reorder_point'      => 60,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catCooking->id,
                'unit_id'            => $mL->id,
                'name'               => 'Datu Puti Vinegar (Suka)',
                'brand'              => 'Datu Puti',
                'size'               => '350 mL',
                'cost_price'         => 1800,   // ₱18
                'retail_price'       => 2200,   // ₱22
                'wholesale_price'    => 2090,   // ₱20.90
                'contractor_price'   => 1980,   // ₱19.80
                'current_stock'      => 360,
                'reorder_point'      => 60,
                'minimum_order_qty'  => 24,
            ],

            // ═══════════════════════════════════════════
            // CANNED & PROCESSED GOODS
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catCanned->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Lucky Me Chicken Noodles (Instant Noodles)',
                'brand'              => 'Monde Nissin',
                'size'               => '55 g pack',
                'cost_price'         => 1090,   // ₱10.90
                'retail_price'       => 1400,   // ₱14
                'wholesale_price'    => 1330,   // ₱13.30
                'contractor_price'   => 1260,   // ₱12.60
                'current_stock'      => 1200,
                'reorder_point'      => 240,
                'minimum_order_qty'  => 60,
            ],
            [
                'category_id'        => $catCanned->id,
                'unit_id'            => $pcs->id,
                'name'               => '555 Sardines in Tomato Sauce',
                'brand'              => '555',
                'size'               => '155 g can',
                'cost_price'         => 2720,   // ₱27.20
                'retail_price'       => 3400,   // ₱34
                'wholesale_price'    => 3230,   // ₱32.30
                'contractor_price'   => 3060,   // ₱30.60
                'current_stock'      => 800,
                'reorder_point'      => 120,
                'minimum_order_qty'  => 48,
            ],
            [
                'category_id'        => $catCanned->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Century Tuna (Flakes in Oil)',
                'brand'              => 'Century Pacific',
                'size'               => '180 g can',
                'cost_price'         => 4960,   // ₱49.60
                'retail_price'       => 6200,   // ₱62
                'wholesale_price'    => 5890,   // ₱58.90
                'contractor_price'   => 5580,   // ₱55.80
                'current_stock'      => 500,
                'reorder_point'      => 80,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catCanned->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Argentina Corned Beef',
                'brand'              => 'Purefoods',
                'size'               => '260 g can',
                'cost_price'         => 6400,   // ₱64
                'retail_price'       => 8000,   // ₱80
                'wholesale_price'    => 7600,   // ₱76
                'contractor_price'   => 7200,   // ₱72
                'current_stock'      => 300,
                'reorder_point'      => 48,
                'minimum_order_qty'  => 24,
            ],

            // ═══════════════════════════════════════════
            // BEVERAGES
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catBev->id,
                'unit_id'            => $sachet->id,
                'name'               => 'Nescafe 3-in-1 Coffee (Original)',
                'brand'              => 'Nestlé',
                'size'               => '20g sachet',
                'cost_price'         => 1000,   // ₱10
                'retail_price'       => 1300,   // ₱13
                'wholesale_price'    => 1235,   // ₱12.35
                'contractor_price'   => 1170,   // ₱11.70
                'current_stock'      => 1500,
                'reorder_point'      => 300,
                'minimum_order_qty'  => 100,
            ],
            [
                'category_id'        => $catBev->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Coca-Cola 1.5L PET Bottle',
                'brand'              => 'Coca-Cola',
                'size'               => '1.5 L',
                'cost_price'         => 5840,   // ₱58.40
                'retail_price'       => 7300,   // ₱73
                'wholesale_price'    => 6935,   // ₱69.35
                'contractor_price'   => 6570,   // ₱65.70
                'current_stock'      => 240,
                'reorder_point'      => 48,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catBev->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Absolute Distilled Water 500mL',
                'brand'              => 'Wilcon',
                'size'               => '500 mL',
                'cost_price'         => 880,    // ₱8.80
                'retail_price'       => 1100,   // ₱11
                'wholesale_price'    => 1045,   // ₱10.45
                'contractor_price'   => 990,    // ₱9.90
                'current_stock'      => 600,
                'reorder_point'      => 120,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catBev->id,
                'unit_id'            => $sachet->id,
                'name'               => 'Tang Powdered Juice (Dalandan)',
                'brand'              => 'Kraft',
                'size'               => '25g sachet',
                'cost_price'         => 640,    // ₱6.40
                'retail_price'       => 800,    // ₱8
                'wholesale_price'    => 760,    // ₱7.60
                'contractor_price'   => 720,    // ₱7.20
                'current_stock'      => 900,
                'reorder_point'      => 150,
                'minimum_order_qty'  => 100,
            ],

            // ═══════════════════════════════════════════
            // PERSONAL CARE & HYGIENE
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catHygiene->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Safeguard Bar Soap (White)',
                'brand'              => 'P&G',
                'size'               => '135 g bar',
                'cost_price'         => 5040,   // ₱50.40
                'retail_price'       => 6300,   // ₱63
                'wholesale_price'    => 5985,   // ₱59.85
                'contractor_price'   => 5670,   // ₱56.70
                'current_stock'      => 500,
                'reorder_point'      => 80,
                'minimum_order_qty'  => 48,
            ],
            [
                'category_id'        => $catHygiene->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Head & Shoulders Shampoo Sachet',
                'brand'              => 'P&G',
                'size'               => '12 mL sachet',
                'cost_price'         => 480,    // ₱4.80
                'retail_price'       => 600,    // ₱6
                'wholesale_price'    => 570,    // ₱5.70
                'contractor_price'   => 540,    // ₱5.40
                'current_stock'      => 2000,
                'reorder_point'      => 400,
                'minimum_order_qty'  => 200,
            ],
            [
                'category_id'        => $catHygiene->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Colgate Toothpaste (Regular)',
                'brand'              => 'Colgate',
                'size'               => '75 mL',
                'cost_price'         => 4960,   // ₱49.60
                'retail_price'       => 6200,   // ₱62
                'wholesale_price'    => 5890,   // ₱58.90
                'contractor_price'   => 5580,   // ₱55.80
                'current_stock'      => 360,
                'reorder_point'      => 60,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catHygiene->id,
                'unit_id'            => $kg->id,
                'name'               => 'Ariel Powder Detergent',
                'brand'              => 'P&G',
                'size'               => '1 kg pack',
                'cost_price'         => 12000,  // ₱120
                'retail_price'       => 15000,  // ₱150
                'wholesale_price'    => 14250,  // ₱142.50
                'contractor_price'   => 13500,  // ₱135
                'current_stock'      => 280,
                'reorder_point'      => 48,
                'minimum_order_qty'  => 12,
            ],

            // ═══════════════════════════════════════════
            // BABY & INFANT NEEDS
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catBaby->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Pampers Baby Dry Diaper (Medium)',
                'brand'              => 'Pampers',
                'size'               => 'Medium (7-12 kg) – 44 pcs',
                'cost_price'         => 50400,  // ₱504
                'retail_price'       => 63000,  // ₱630
                'wholesale_price'    => 59850,  // ₱598.50
                'contractor_price'   => 56700,  // ₱567
                'current_stock'      => 60,
                'reorder_point'      => 10,
                'minimum_order_qty'  => 6,
            ],
            [
                'category_id'        => $catBaby->id,
                'unit_id'            => $kg->id,
                'name'               => 'Bear Brand Adult Plus Powdered Milk',
                'brand'              => 'Nestlé',
                'size'               => '1 kg pack',
                'cost_price'         => 38400,  // ₱384
                'retail_price'       => 48000,  // ₱480
                'wholesale_price'    => 45600,  // ₱456
                'contractor_price'   => 43200,  // ₱432
                'current_stock'      => 100,
                'reorder_point'      => 20,
                'minimum_order_qty'  => 6,
            ],

            // ═══════════════════════════════════════════
            // SNACKS & CONFECTIONERY
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catSnacks->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Rebisco Crackers (Skyflakes)',
                'brand'              => 'Rebisco',
                'size'               => '250 g pack',
                'cost_price'         => 3360,   // ₱33.60
                'retail_price'       => 4200,   // ₱42
                'wholesale_price'    => 3990,   // ₱39.90
                'contractor_price'   => 3780,   // ₱37.80
                'current_stock'      => 400,
                'reorder_point'      => 60,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catSnacks->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Jack & Jill Potato Chips (Original)',
                'brand'              => 'Jack & Jill',
                'size'               => '60 g bag',
                'cost_price'         => 2000,   // ₱20
                'retail_price'       => 2500,   // ₱25
                'wholesale_price'    => 2375,   // ₱23.75
                'contractor_price'   => 2250,   // ₱22.50
                'current_stock'      => 500,
                'reorder_point'      => 80,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catSnacks->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Gardenia Classic White Bread (Large)',
                'brand'              => 'Gardenia',
                'size'               => '600 g loaf',
                'cost_price'         => 5200,   // ₱52
                'retail_price'       => 6500,   // ₱65
                'wholesale_price'    => 6175,   // ₱61.75
                'contractor_price'   => 5850,   // ₱58.50
                'current_stock'      => 80,
                'reorder_point'      => 15,
                'minimum_order_qty'  => 10,
            ],

            // ═══════════════════════════════════════════
            // GENERAL MERCHANDISE
            // ═══════════════════════════════════════════
            [
                'category_id'        => $catGeneral->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Eveready AA Batteries (2-pack)',
                'brand'              => 'Eveready',
                'size'               => '2 pcs/pack',
                'cost_price'         => 4800,   // ₱48
                'retail_price'       => 6000,   // ₱60
                'wholesale_price'    => 5700,   // ₱57
                'contractor_price'   => 5400,   // ₱54
                'current_stock'      => 200,
                'reorder_point'      => 40,
                'minimum_order_qty'  => 24,
            ],
            [
                'category_id'        => $catGeneral->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Wax Candle 4-inch (per piece)',
                'brand'              => 'Generic',
                'size'               => '4 inch',
                'cost_price'         => 480,    // ₱4.80
                'retail_price'       => 600,    // ₱6
                'wholesale_price'    => 570,    // ₱5.70
                'contractor_price'   => 540,    // ₱5.40
                'current_stock'      => 1000,
                'reorder_point'      => 200,
                'minimum_order_qty'  => 100,
            ],
            [
                'category_id'        => $catGeneral->id,
                'unit_id'            => $box->id,
                'name'               => 'Strike Matchbox (Posporo)',
                'brand'              => 'Strike',
                'size'               => '40 matchsticks/box',
                'cost_price'         => 240,    // ₱2.40
                'retail_price'       => 300,    // ₱3
                'wholesale_price'    => 285,    // ₱2.85
                'contractor_price'   => 270,    // ₱2.70
                'current_stock'      => 1500,
                'reorder_point'      => 300,
                'minimum_order_qty'  => 200,
            ],
            [
                'category_id'        => $catGeneral->id,
                'unit_id'            => $pcs->id,
                'name'               => 'Intermediate Pad Paper',
                'brand'              => 'Pad',
                'size'               => '40-leaf intermediate',
                'cost_price'         => 2640,   // ₱26.40
                'retail_price'       => 3300,   // ₱33
                'wholesale_price'    => 3135,   // ₱31.35
                'contractor_price'   => 2970,   // ₱29.70
                'current_stock'      => 300,
                'reorder_point'      => 50,
                'minimum_order_qty'  => 24,
            ],
        ];

        $skuCounter = 1001;
        foreach ($products as $product) {
            Product::create([
                'uuid'                => Str::uuid(),
                'store_id'            => $store->id,
                'category_id'         => $product['category_id'],
                'unit_id'             => $product['unit_id'],
                'name'                => $product['name'],
                'sku'                 => 'SNLSI-' . $skuCounter++,
                'brand'               => $product['brand'] ?? null,
                'size'                => $product['size'] ?? null,
                'material'            => $product['material'] ?? null,
                'color'               => $product['color'] ?? null,
                'cost_price'          => $product['cost_price'],
                'retail_price'        => $product['retail_price'],
                'wholesale_price'     => $product['wholesale_price'],
                'contractor_price'    => $product['contractor_price'],
                'current_stock'       => $product['current_stock'],
                'reorder_point'       => $product['reorder_point'],
                'minimum_order_qty'   => $product['minimum_order_qty'],
                'is_active'           => true,
                'is_vat_exempt'       => false,
                'track_inventory'     => true,
                'allow_negative_stock' => false,
            ]);
        }
    }
}
