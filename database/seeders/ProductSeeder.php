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

        // Get categories
        $cementCategory = Category::where('slug', 'cement-concrete')->first();
        $steelCategory = Category::where('slug', 'steel-rebar')->first();
        $lumberCategory = Category::where('slug', 'lumber-wood')->first();
        $paintCategory = Category::where('slug', 'paint-coatings')->first();
        $plumbingCategory = Category::where('slug', 'plumbing')->first();
        $electricalCategory = Category::where('slug', 'electrical')->first();
        $toolsCategory = Category::where('slug', 'tools-equipment')->first();
        $nailsCategory = Category::where('slug', 'nails-fasteners')->first();
        $roofingCategory = Category::where('slug', 'roofing-ceiling')->first();
        $sandCategory = Category::where('slug', 'sand-gravel')->first();
        $blocksCategory = Category::where('slug', 'hollow-blocks-masonry')->first();
        $glassCategory = Category::where('slug', 'glass-mirrors')->first();
        $tilesCategory = Category::where('slug', 'tiles-flooring')->first();
        $safetyCategory = Category::where('slug', 'safety-gear')->first();
        $generalCategory = Category::where('slug', 'general-hardware')->first();

        // Get units
        $pcs = UnitOfMeasure::where('abbreviation', 'pcs')->first();
        $bag = UnitOfMeasure::where('abbreviation', 'bag')->first();
        $sack = UnitOfMeasure::where('abbreviation', 'sack')->first();
        $kg = UnitOfMeasure::where('abbreviation', 'kg')->first();
        $meter = UnitOfMeasure::where('abbreviation', 'm')->first();
        $foot = UnitOfMeasure::where('abbreviation', 'ft')->first();
        $gallon = UnitOfMeasure::where('abbreviation', 'gal')->first();
        $liter = UnitOfMeasure::where('abbreviation', 'L')->first();
        $roll = UnitOfMeasure::where('abbreviation', 'roll')->first();
        $box = UnitOfMeasure::where('abbreviation', 'box')->first();
        $bundle = UnitOfMeasure::where('abbreviation', 'bundle')->first();
        $length = UnitOfMeasure::where('abbreviation', 'length')->first();
        $sheet = UnitOfMeasure::where('abbreviation', 'sheet')->first();
        $sqm = UnitOfMeasure::where('abbreviation', 'sq.m')->first();
        $cum = UnitOfMeasure::where('abbreviation', 'cu.m')->first();

        $products = [
            // CEMENT & CONCRETE (3 products)
            [
                'category_id' => $cementCategory->id,
                'unit_id' => $bag->id,
                'name' => 'Holcim Portland Cement',
                'brand' => 'Holcim',
                'size' => '40kg',
                'cost_price' => 23800, // ₱238 (85% of retail)
                'retail_price' => 28000, // ₱280
                'wholesale_price' => 26600, // ₱266 (95%)
                'contractor_price' => 25200, // ₱252 (90%)
                'current_stock' => 2500,
                'reorder_point' => 500,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $cementCategory->id,
                'unit_id' => $bag->id,
                'name' => 'Eagle Portland Cement',
                'brand' => 'Eagle',
                'size' => '40kg',
                'cost_price' => 22525, // ₱225.25 (85%)
                'retail_price' => 26500, // ₱265
                'wholesale_price' => 25175, // ₱251.75 (95%)
                'contractor_price' => 23850, // ₱238.50 (90%)
                'current_stock' => 3200,
                'reorder_point' => 600,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $cementCategory->id,
                'unit_id' => $bag->id,
                'name' => 'Republic Portland Cement',
                'brand' => 'Republic',
                'size' => '40kg',
                'cost_price' => 22950, // ₱229.50 (85%)
                'retail_price' => 27000, // ₱270
                'wholesale_price' => 25650, // ₱256.50 (95%)
                'contractor_price' => 24300, // ₱243 (90%)
                'current_stock' => 2800,
                'reorder_point' => 550,
                'minimum_order_qty' => 50,
            ],

            // STEEL & REBAR (6 products)
            [
                'category_id' => $steelCategory->id,
                'unit_id' => $length->id,
                'name' => 'Deformed Bar 10mm x 6m',
                'brand' => 'SteelAsia',
                'size' => '10mm x 6m',
                'cost_price' => 15725, // ₱157.25 (85%)
                'retail_price' => 18500, // ₱185
                'wholesale_price' => 17575, // ₱175.75 (95%)
                'contractor_price' => 16650, // ₱166.50 (90%)
                'current_stock' => 850,
                'reorder_point' => 150,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $steelCategory->id,
                'unit_id' => $length->id,
                'name' => 'Deformed Bar 12mm x 6m',
                'brand' => 'SteelAsia',
                'size' => '12mm x 6m',
                'cost_price' => 22525, // ₱225.25 (85%)
                'retail_price' => 26500, // ₱265
                'wholesale_price' => 25175, // ₱251.75 (95%)
                'contractor_price' => 23850, // ₱238.50 (90%)
                'current_stock' => 720,
                'reorder_point' => 120,
                'minimum_order_qty' => 30,
            ],
            [
                'category_id' => $steelCategory->id,
                'unit_id' => $length->id,
                'name' => 'Deformed Bar 16mm x 6m',
                'brand' => 'SteelAsia',
                'size' => '16mm x 6m',
                'cost_price' => 39950, // ₱399.50 (85%)
                'retail_price' => 47000, // ₱470
                'wholesale_price' => 44650, // ₱446.50 (95%)
                'contractor_price' => 42300, // ₱423 (90%)
                'current_stock' => 480,
                'reorder_point' => 80,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $steelCategory->id,
                'unit_id' => $length->id,
                'name' => 'Deformed Bar 20mm x 6m',
                'brand' => 'SteelAsia',
                'size' => '20mm x 6m',
                'cost_price' => 62050, // ₱620.50 (85%)
                'retail_price' => 73000, // ₱730
                'wholesale_price' => 69350, // ₱693.50 (95%)
                'contractor_price' => 65700, // ₱657 (90%)
                'current_stock' => 320,
                'reorder_point' => 60,
                'minimum_order_qty' => 15,
            ],
            [
                'category_id' => $steelCategory->id,
                'unit_id' => $kg->id,
                'name' => 'Steel Plate 3mm Thickness',
                'brand' => 'SteelAsia',
                'size' => '3mm',
                'material' => 'Steel',
                'cost_price' => 5950, // ₱59.50 (85%)
                'retail_price' => 7000, // ₱70
                'wholesale_price' => 6650, // ₱66.50 (95%)
                'contractor_price' => 6300, // ₱63 (90%)
                'current_stock' => 1500,
                'reorder_point' => 300,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $steelCategory->id,
                'unit_id' => $length->id,
                'name' => 'Angle Bar 1" x 1" x 6m',
                'brand' => 'SteelAsia',
                'size' => '1" x 1" x 6m',
                'cost_price' => 21250, // ₱212.50 (85%)
                'retail_price' => 25000, // ₱250
                'wholesale_price' => 23750, // ₱237.50 (95%)
                'contractor_price' => 22500, // ₱225 (90%)
                'current_stock' => 280,
                'reorder_point' => 50,
                'minimum_order_qty' => 20,
            ],

            // LUMBER & WOOD (7 products)
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Yakal Lumber 2x3x10',
                'brand' => null,
                'size' => '2" x 3" x 10ft',
                'material' => 'Yakal',
                'cost_price' => 14025, // ₱140.25 (85%)
                'retail_price' => 16500, // ₱165
                'wholesale_price' => 15675, // ₱156.75 (95%)
                'contractor_price' => 14850, // ₱148.50 (90%)
                'current_stock' => 450,
                'reorder_point' => 80,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Yakal Lumber 2x4x10',
                'brand' => null,
                'size' => '2" x 4" x 10ft',
                'material' => 'Yakal',
                'cost_price' => 18700, // ₱187 (85%)
                'retail_price' => 22000, // ₱220
                'wholesale_price' => 20900, // ₱209 (95%)
                'contractor_price' => 19800, // ₱198 (90%)
                'current_stock' => 380,
                'reorder_point' => 70,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Coconut Lumber 2x2x8',
                'brand' => null,
                'size' => '2" x 2" x 8ft',
                'material' => 'Coconut Wood',
                'cost_price' => 5100, // ₱51 (85%)
                'retail_price' => 6000, // ₱60
                'wholesale_price' => 5700, // ₱57 (95%)
                'contractor_price' => 5400, // ₱54 (90%)
                'current_stock' => 680,
                'reorder_point' => 120,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $sheet->id,
                'name' => 'Marine Plywood 1/4" x 4x8',
                'brand' => null,
                'size' => '1/4" x 4ft x 8ft',
                'material' => 'Marine Plywood',
                'cost_price' => 40800, // ₱408 (85%)
                'retail_price' => 48000, // ₱480
                'wholesale_price' => 45600, // ₱456 (95%)
                'contractor_price' => 43200, // ₱432 (90%)
                'current_stock' => 220,
                'reorder_point' => 40,
                'minimum_order_qty' => 10,
            ],
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $sheet->id,
                'name' => 'Marine Plywood 1/2" x 4x8',
                'brand' => null,
                'size' => '1/2" x 4ft x 8ft',
                'material' => 'Marine Plywood',
                'cost_price' => 63750, // ₱637.50 (85%)
                'retail_price' => 75000, // ₱750
                'wholesale_price' => 71250, // ₱712.50 (95%)
                'contractor_price' => 67500, // ₱675 (90%)
                'current_stock' => 180,
                'reorder_point' => 35,
                'minimum_order_qty' => 10,
            ],
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $sheet->id,
                'name' => 'Ordinary Plywood 1/4" x 4x8',
                'brand' => null,
                'size' => '1/4" x 4ft x 8ft',
                'material' => 'Ordinary Plywood',
                'cost_price' => 32300, // ₱323 (85%)
                'retail_price' => 38000, // ₱380
                'wholesale_price' => 36100, // ₱361 (95%)
                'contractor_price' => 34200, // ₱342 (90%)
                'current_stock' => 250,
                'reorder_point' => 45,
                'minimum_order_qty' => 10,
            ],
            [
                'category_id' => $lumberCategory->id,
                'unit_id' => $sheet->id,
                'name' => 'Lawanit Board 1/4" x 4x8',
                'brand' => null,
                'size' => '1/4" x 4ft x 8ft',
                'material' => 'Hardboard',
                'cost_price' => 25500, // ₱255 (85%)
                'retail_price' => 30000, // ₱300
                'wholesale_price' => 28500, // ₱285 (95%)
                'contractor_price' => 27000, // ₱270 (90%)
                'current_stock' => 195,
                'reorder_point' => 40,
                'minimum_order_qty' => 10,
            ],

            // PAINT & COATINGS (5 products)
            [
                'category_id' => $paintCategory->id,
                'unit_id' => $gallon->id,
                'name' => 'Boysen Latex White',
                'brand' => 'Boysen',
                'size' => '1 Gallon',
                'color' => 'White',
                'cost_price' => 48875, // ₱488.75 (85%)
                'retail_price' => 57500, // ₱575
                'wholesale_price' => 54625, // ₱546.25 (95%)
                'contractor_price' => 51750, // ₱517.50 (90%)
                'current_stock' => 185,
                'reorder_point' => 30,
                'minimum_order_qty' => 10,
            ],
            [
                'category_id' => $paintCategory->id,
                'unit_id' => $gallon->id,
                'name' => 'Boysen Permacoat Latex',
                'brand' => 'Boysen',
                'size' => '1 Gallon',
                'color' => 'White',
                'cost_price' => 41225, // ₱412.25 (85%)
                'retail_price' => 48500, // ₱485
                'wholesale_price' => 46075, // ₱460.75 (95%)
                'contractor_price' => 43650, // ₱436.50 (90%)
                'current_stock' => 210,
                'reorder_point' => 35,
                'minimum_order_qty' => 10,
            ],
            [
                'category_id' => $paintCategory->id,
                'unit_id' => $gallon->id,
                'name' => 'Davies Semi-Gloss Enamel',
                'brand' => 'Davies',
                'size' => '1 Gallon',
                'color' => 'White',
                'cost_price' => 52700, // ₱527 (85%)
                'retail_price' => 62000, // ₱620
                'wholesale_price' => 58900, // ₱589 (95%)
                'contractor_price' => 55800, // ₱558 (90%)
                'current_stock' => 165,
                'reorder_point' => 28,
                'minimum_order_qty' => 8,
            ],
            [
                'category_id' => $paintCategory->id,
                'unit_id' => $liter->id,
                'name' => 'Paint Thinner',
                'brand' => 'Generic',
                'size' => '1 Liter',
                'cost_price' => 6375, // ₱63.75 (85%)
                'retail_price' => 7500, // ₱75
                'wholesale_price' => 7125, // ₱71.25 (95%)
                'contractor_price' => 6750, // ₱67.50 (90%)
                'current_stock' => 420,
                'reorder_point' => 80,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $paintCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Paint Roller 9 inch',
                'brand' => 'Generic',
                'size' => '9 inch',
                'cost_price' => 4250, // ₱42.50 (85%)
                'retail_price' => 5000, // ₱50
                'wholesale_price' => 4750, // ₱47.50 (95%)
                'contractor_price' => 4500, // ₱45 (90%)
                'current_stock' => 350,
                'reorder_point' => 60,
                'minimum_order_qty' => 50,
            ],

            // PLUMBING (8 products)
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $length->id,
                'name' => 'PVC Pipe 1/2" x 10ft',
                'brand' => 'Pacific',
                'size' => '1/2" x 10ft',
                'cost_price' => 6800, // ₱68 (85%)
                'retail_price' => 8000, // ₱80
                'wholesale_price' => 7600, // ₱76 (95%)
                'contractor_price' => 7200, // ₱72 (90%)
                'current_stock' => 580,
                'reorder_point' => 100,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $length->id,
                'name' => 'PVC Pipe 3/4" x 10ft',
                'brand' => 'Pacific',
                'size' => '3/4" x 10ft',
                'cost_price' => 10200, // ₱102 (85%)
                'retail_price' => 12000, // ₱120
                'wholesale_price' => 11400, // ₱114 (95%)
                'contractor_price' => 10800, // ₱108 (90%)
                'current_stock' => 520,
                'reorder_point' => 90,
                'minimum_order_qty' => 40,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $length->id,
                'name' => 'PVC Pipe 1" x 10ft',
                'brand' => 'Pacific',
                'size' => '1" x 10ft',
                'cost_price' => 14450, // ₱144.50 (85%)
                'retail_price' => 17000, // ₱170
                'wholesale_price' => 16150, // ₱161.50 (95%)
                'contractor_price' => 15300, // ₱153 (90%)
                'current_stock' => 460,
                'reorder_point' => 80,
                'minimum_order_qty' => 30,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'PVC Elbow 1/2"',
                'brand' => 'Pacific',
                'size' => '1/2"',
                'cost_price' => 425, // ₱4.25 (85%)
                'retail_price' => 500, // ₱5
                'wholesale_price' => 475, // ₱4.75 (95%)
                'contractor_price' => 450, // ₱4.50 (90%)
                'current_stock' => 1250,
                'reorder_point' => 250,
                'minimum_order_qty' => 100,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'PVC Elbow 3/4"',
                'brand' => 'Pacific',
                'size' => '3/4"',
                'cost_price' => 680, // ₱6.80 (85%)
                'retail_price' => 800, // ₱8
                'wholesale_price' => 760, // ₱7.60 (95%)
                'contractor_price' => 720, // ₱7.20 (90%)
                'current_stock' => 1150,
                'reorder_point' => 230,
                'minimum_order_qty' => 100,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'PVC Tee 1/2"',
                'brand' => 'Pacific',
                'size' => '1/2"',
                'cost_price' => 595, // ₱5.95 (85%)
                'retail_price' => 700, // ₱7
                'wholesale_price' => 665, // ₱6.65 (95%)
                'contractor_price' => 630, // ₱6.30 (90%)
                'current_stock' => 980,
                'reorder_point' => 200,
                'minimum_order_qty' => 100,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Ball Valve 1/2"',
                'brand' => 'Generic',
                'size' => '1/2"',
                'cost_price' => 4250, // ₱42.50 (85%)
                'retail_price' => 5000, // ₱50
                'wholesale_price' => 4750, // ₱47.50 (95%)
                'contractor_price' => 4500, // ₱45 (90%)
                'current_stock' => 320,
                'reorder_point' => 60,
                'minimum_order_qty' => 30,
            ],
            [
                'category_id' => $plumbingCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Water Tank 500L',
                'brand' => 'Neltex',
                'size' => '500 Liters',
                'cost_price' => 255000, // ₱2,550 (85%)
                'retail_price' => 300000, // ₱3,000
                'wholesale_price' => 285000, // ₱2,850 (95%)
                'contractor_price' => 270000, // ₱2,700 (90%)
                'current_stock' => 45,
                'reorder_point' => 8,
                'minimum_order_qty' => 3,
            ],

            // ELECTRICAL (6 products)
            [
                'category_id' => $electricalCategory->id,
                'unit_id' => $roll->id,
                'name' => 'Electrical Wire 2.0mm',
                'brand' => 'Generic',
                'size' => '2.0mm x 100m',
                'cost_price' => 76500, // ₱765 (85%)
                'retail_price' => 90000, // ₱900
                'wholesale_price' => 85500, // ₱855 (95%)
                'contractor_price' => 81000, // ₱810 (90%)
                'current_stock' => 85,
                'reorder_point' => 15,
                'minimum_order_qty' => 5,
            ],
            [
                'category_id' => $electricalCategory->id,
                'unit_id' => $roll->id,
                'name' => 'Electrical Wire 3.5mm',
                'brand' => 'Generic',
                'size' => '3.5mm x 100m',
                'cost_price' => 127500, // ₱1,275 (85%)
                'retail_price' => 150000, // ₱1,500
                'wholesale_price' => 142500, // ₱1,425 (95%)
                'contractor_price' => 135000, // ₱1,350 (90%)
                'current_stock' => 62,
                'reorder_point' => 12,
                'minimum_order_qty' => 5,
            ],
            [
                'category_id' => $electricalCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Circuit Breaker 20A',
                'brand' => 'Generic',
                'size' => '20 Ampere',
                'cost_price' => 10200, // ₱102 (85%)
                'retail_price' => 12000, // ₱120
                'wholesale_price' => 11400, // ₱114 (95%)
                'contractor_price' => 10800, // ₱108 (90%)
                'current_stock' => 280,
                'reorder_point' => 50,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $electricalCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Convenience Outlet',
                'brand' => 'Generic',
                'size' => 'Standard',
                'cost_price' => 2550, // ₱25.50 (85%)
                'retail_price' => 3000, // ₱30
                'wholesale_price' => 2850, // ₱28.50 (95%)
                'contractor_price' => 2700, // ₱27 (90%)
                'current_stock' => 650,
                'reorder_point' => 120,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $electricalCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Light Switch Single Gang',
                'brand' => 'Generic',
                'size' => '1 Gang',
                'cost_price' => 2125, // ₱21.25 (85%)
                'retail_price' => 2500, // ₱25
                'wholesale_price' => 2375, // ₱23.75 (95%)
                'contractor_price' => 2250, // ₱22.50 (90%)
                'current_stock' => 720,
                'reorder_point' => 140,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $electricalCategory->id,
                'unit_id' => $length->id,
                'name' => 'PVC Conduit 1/2" x 10ft',
                'brand' => 'Generic',
                'size' => '1/2" x 10ft',
                'cost_price' => 5950, // ₱59.50 (85%)
                'retail_price' => 7000, // ₱70
                'wholesale_price' => 6650, // ₱66.50 (95%)
                'contractor_price' => 6300, // ₱63 (90%)
                'current_stock' => 420,
                'reorder_point' => 75,
                'minimum_order_qty' => 30,
            ],

            // NAILS & FASTENERS (4 products)
            [
                'category_id' => $nailsCategory->id,
                'unit_id' => $kg->id,
                'name' => 'Common Wire Nails 2"',
                'brand' => 'Generic',
                'size' => '2 inch',
                'cost_price' => 6800, // ₱68 (85%)
                'retail_price' => 8000, // ₱80
                'wholesale_price' => 7600, // ₱76 (95%)
                'contractor_price' => 7200, // ₱72 (90%)
                'current_stock' => 850,
                'reorder_point' => 160,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $nailsCategory->id,
                'unit_id' => $kg->id,
                'name' => 'Common Wire Nails 3"',
                'brand' => 'Generic',
                'size' => '3 inch',
                'cost_price' => 6800, // ₱68 (85%)
                'retail_price' => 8000, // ₱80
                'wholesale_price' => 7600, // ₱76 (95%)
                'contractor_price' => 7200, // ₱72 (90%)
                'current_stock' => 920,
                'reorder_point' => 180,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $nailsCategory->id,
                'unit_id' => $box->id,
                'name' => 'Concrete Nails 3"',
                'brand' => 'Generic',
                'size' => '3 inch',
                'cost_price' => 8500, // ₱85 (85%)
                'retail_price' => 10000, // ₱100
                'wholesale_price' => 9500, // ₱95 (95%)
                'contractor_price' => 9000, // ₱90 (90%)
                'current_stock' => 380,
                'reorder_point' => 70,
                'minimum_order_qty' => 30,
            ],
            [
                'category_id' => $nailsCategory->id,
                'unit_id' => $box->id,
                'name' => 'Self-Tapping Screws 1"',
                'brand' => 'Generic',
                'size' => '1 inch',
                'cost_price' => 12750, // ₱127.50 (85%)
                'retail_price' => 15000, // ₱150
                'wholesale_price' => 14250, // ₱142.50 (95%)
                'contractor_price' => 13500, // ₱135 (90%)
                'current_stock' => 280,
                'reorder_point' => 50,
                'minimum_order_qty' => 20,
            ],

            // ROOFING & CEILING (3 products)
            [
                'category_id' => $roofingCategory->id,
                'unit_id' => $sheet->id,
                'name' => 'GI Corrugated Sheet 0.5mm x 2x8',
                'brand' => 'Generic',
                'size' => '0.5mm x 2ft x 8ft',
                'material' => 'Galvanized Iron',
                'cost_price' => 38250, // ₱382.50 (85%)
                'retail_price' => 45000, // ₱450
                'wholesale_price' => 42750, // ₱427.50 (95%)
                'contractor_price' => 40500, // ₱405 (90%)
                'current_stock' => 320,
                'reorder_point' => 60,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $roofingCategory->id,
                'unit_id' => $sheet->id,
                'name' => 'Ceiling Board 4x8',
                'brand' => 'Generic',
                'size' => '4ft x 8ft',
                'cost_price' => 19550, // ₱195.50 (85%)
                'retail_price' => 23000, // ₱230
                'wholesale_price' => 21850, // ₱218.50 (95%)
                'contractor_price' => 20700, // ₱207 (90%)
                'current_stock' => 250,
                'reorder_point' => 45,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $roofingCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Roofing Screw with Washer',
                'brand' => 'Generic',
                'size' => '1 inch',
                'cost_price' => 170, // ₱1.70 (85%)
                'retail_price' => 200, // ₱2
                'wholesale_price' => 190, // ₱1.90 (95%)
                'contractor_price' => 180, // ₱1.80 (90%)
                'current_stock' => 3500,
                'reorder_point' => 700,
                'minimum_order_qty' => 500,
            ],

            // SAND & GRAVEL (3 products)
            [
                'category_id' => $sandCategory->id,
                'unit_id' => $cum->id,
                'name' => 'River Sand',
                'brand' => null,
                'size' => 'Per Cubic Meter',
                'cost_price' => 68000, // ₱680 (85%)
                'retail_price' => 80000, // ₱800
                'wholesale_price' => 76000, // ₱760 (95%)
                'contractor_price' => 72000, // ₱720 (90%)
                'current_stock' => 15,
                'reorder_point' => 3,
                'minimum_order_qty' => 2,
            ],
            [
                'category_id' => $sandCategory->id,
                'unit_id' => $cum->id,
                'name' => 'Washed Sand',
                'brand' => null,
                'size' => 'Per Cubic Meter',
                'cost_price' => 93500, // ₱935 (85%)
                'retail_price' => 110000, // ₱1,100
                'wholesale_price' => 104500, // ₱1,045 (95%)
                'contractor_price' => 99000, // ₱990 (90%)
                'current_stock' => 12,
                'reorder_point' => 3,
                'minimum_order_qty' => 2,
            ],
            [
                'category_id' => $sandCategory->id,
                'unit_id' => $cum->id,
                'name' => 'Gravel 3/4"',
                'brand' => null,
                'size' => '3/4 inch',
                'cost_price' => 85000, // ₱850 (85%)
                'retail_price' => 100000, // ₱1,000
                'wholesale_price' => 95000, // ₱950 (95%)
                'contractor_price' => 90000, // ₱900 (90%)
                'current_stock' => 18,
                'reorder_point' => 4,
                'minimum_order_qty' => 2,
            ],

            // HOLLOW BLOCKS & MASONRY (2 products)
            [
                'category_id' => $blocksCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'CHB 4" Standard',
                'brand' => null,
                'size' => '4 inch',
                'cost_price' => 935, // ₱9.35 (85%)
                'retail_price' => 1100, // ₱11
                'wholesale_price' => 1045, // ₱10.45 (95%)
                'contractor_price' => 990, // ₱9.90 (90%)
                'current_stock' => 4500,
                'reorder_point' => 800,
                'minimum_order_qty' => 500,
            ],
            [
                'category_id' => $blocksCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'CHB 6" Standard',
                'brand' => null,
                'size' => '6 inch',
                'cost_price' => 1360, // ₱13.60 (85%)
                'retail_price' => 1600, // ₱16
                'wholesale_price' => 1520, // ₱15.20 (95%)
                'contractor_price' => 1440, // ₱14.40 (90%)
                'current_stock' => 3800,
                'reorder_point' => 700,
                'minimum_order_qty' => 400,
            ],

            // SAFETY GEAR (3 products)
            [
                'category_id' => $safetyCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Safety Helmet',
                'brand' => 'Generic',
                'size' => 'Standard',
                'cost_price' => 12750, // ₱127.50 (85%)
                'retail_price' => 15000, // ₱150
                'wholesale_price' => 14250, // ₱142.50 (95%)
                'contractor_price' => 13500, // ₱135 (90%)
                'current_stock' => 180,
                'reorder_point' => 35,
                'minimum_order_qty' => 20,
            ],
            [
                'category_id' => $safetyCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Work Gloves Leather',
                'brand' => 'Generic',
                'size' => 'Large',
                'cost_price' => 3400, // ₱34 (85%)
                'retail_price' => 4000, // ₱40
                'wholesale_price' => 3800, // ₱38 (95%)
                'contractor_price' => 3600, // ₱36 (90%)
                'current_stock' => 320,
                'reorder_point' => 60,
                'minimum_order_qty' => 50,
            ],
            [
                'category_id' => $safetyCategory->id,
                'unit_id' => $pcs->id,
                'name' => 'Safety Goggles',
                'brand' => 'Generic',
                'size' => 'Standard',
                'cost_price' => 4250, // ₱42.50 (85%)
                'retail_price' => 5000, // ₱50
                'wholesale_price' => 4750, // ₱47.50 (95%)
                'contractor_price' => 4500, // ₱45 (90%)
                'current_stock' => 220,
                'reorder_point' => 40,
                'minimum_order_qty' => 30,
            ],
        ];

        $skuCounter = 1001;
        foreach ($products as $product) {
            Product::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'category_id' => $product['category_id'],
                'unit_id' => $product['unit_id'],
                'name' => $product['name'],
                'sku' => 'JMH-' . $skuCounter++,
                'brand' => $product['brand'] ?? null,
                'size' => $product['size'] ?? null,
                'material' => $product['material'] ?? null,
                'color' => $product['color'] ?? null,
                'cost_price' => $product['cost_price'],
                'retail_price' => $product['retail_price'],
                'wholesale_price' => $product['wholesale_price'],
                'contractor_price' => $product['contractor_price'],
                'current_stock' => $product['current_stock'],
                'reorder_point' => $product['reorder_point'],
                'minimum_order_qty' => $product['minimum_order_qty'],
                'is_active' => true,
                'is_vat_exempt' => false,
                'track_inventory' => true,
                'allow_negative_stock' => false,
            ]);
        }
    }
}
