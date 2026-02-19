<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();

        $categories = [
            ['name' => 'Cement & Concrete', 'slug' => 'cement-concrete', 'description' => 'Portland cement, quick-setting cement, concrete additives', 'sort_order' => 1],
            ['name' => 'Steel & Rebar', 'slug' => 'steel-rebar', 'description' => 'Deformed bars, plain bars, steel plates, angle bars', 'sort_order' => 2],
            ['name' => 'Lumber & Wood', 'slug' => 'lumber-wood', 'description' => 'Construction lumber, plywood, marine wood, coconut lumber', 'sort_order' => 3],
            ['name' => 'Paint & Coatings', 'slug' => 'paint-coatings', 'description' => 'Latex paint, enamel, primer, thinner, paint accessories', 'sort_order' => 4],
            ['name' => 'Plumbing', 'slug' => 'plumbing', 'description' => 'PVC pipes, fittings, elbows, valves, faucets, water tanks', 'sort_order' => 5],
            ['name' => 'Electrical', 'slug' => 'electrical', 'description' => 'Wires, cables, switches, outlets, breakers, conduits', 'sort_order' => 6],
            ['name' => 'Tools & Equipment', 'slug' => 'tools-equipment', 'description' => 'Hand tools, power tools, measuring tools, safety equipment', 'sort_order' => 7],
            ['name' => 'Nails & Fasteners', 'slug' => 'nails-fasteners', 'description' => 'Common nails, finishing nails, screws, bolts, anchors', 'sort_order' => 8],
            ['name' => 'Roofing & Ceiling', 'slug' => 'roofing-ceiling', 'description' => 'GI sheets, corrugated roofing, ceiling boards, gutters', 'sort_order' => 9],
            ['name' => 'Sand & Gravel', 'slug' => 'sand-gravel', 'description' => 'Construction sand, gravel, crushed stone, filling materials', 'sort_order' => 10],
            ['name' => 'Hollow Blocks & Masonry', 'slug' => 'hollow-blocks-masonry', 'description' => 'CHB, bricks, pavers, concrete blocks, interlocking blocks', 'sort_order' => 11],
            ['name' => 'Glass & Mirrors', 'slug' => 'glass-mirrors', 'description' => 'Clear glass, tinted glass, mirrors, glass accessories', 'sort_order' => 12],
            ['name' => 'Tiles & Flooring', 'slug' => 'tiles-flooring', 'description' => 'Floor tiles, wall tiles, ceramic, porcelain, adhesives', 'sort_order' => 13],
            ['name' => 'Safety Gear', 'slug' => 'safety-gear', 'description' => 'Helmets, gloves, boots, goggles, masks, safety vests', 'sort_order' => 14],
            ['name' => 'General Hardware', 'slug' => 'general-hardware', 'description' => 'Padlocks, hinges, door knobs, chains, rope, misc items', 'sort_order' => 15],
        ];

        foreach ($categories as $category) {
            Category::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'sort_order' => $category['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}
