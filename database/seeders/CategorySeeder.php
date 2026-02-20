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

        // ── Agri-Supply categories ──────────────────────────────────────────────
        // ── Grocery / Sari-sari categories ─────────────────────────────────────
        $categories = [
            // AGRI SUPPLY
            ['name' => 'Seeds & Planting Materials',   'slug' => 'seeds-planting-materials',  'description' => 'Certified rice, corn, vegetable & root crop seeds; seedlings',                 'sort_order' => 1],
            ['name' => 'Fertilizers',                   'slug' => 'fertilizers',                'description' => 'Complete, urea, ammonium sulfate, foliar, organic compost',                   'sort_order' => 2],
            ['name' => 'Pesticides & Herbicides',       'slug' => 'pesticides-herbicides',      'description' => 'Insecticides, fungicides, rodenticides, herbicides, molluscicides',           'sort_order' => 3],
            ['name' => 'Farm Tools & Equipment',        'slug' => 'farm-tools-equipment',       'description' => 'Hand tools, sprayers, harvesting equipment, irrigation accessories',          'sort_order' => 4],
            ['name' => 'Animal Feeds & Veterinary',     'slug' => 'animal-feeds-veterinary',    'description' => 'Poultry feeds, swine feeds, cattle feeds, vitamins, vaccines',                'sort_order' => 5],
            ['name' => 'Fishing Supplies',              'slug' => 'fishing-supplies',           'description' => 'Fish feeds, nets, hooks, fishing lines, aquaculture supplies',                'sort_order' => 6],
            ['name' => 'Farm Packaging & Storage',      'slug' => 'farm-packaging-storage',     'description' => 'Sacks, twine, plastic crates, tarpaulin, polypropylene bags',                'sort_order' => 7],
            // GROCERY
            ['name' => 'Rice & Grains',                 'slug' => 'rice-grains',                'description' => 'Milled rice (NFA & commercial), corn grits, oats, monggo',                  'sort_order' => 8],
            ['name' => 'Cooking Essentials',            'slug' => 'cooking-essentials',         'description' => 'Cooking oil, vinegar, soy sauce, fish sauce, salt, sugar, flour',            'sort_order' => 9],
            ['name' => 'Canned & Processed Goods',      'slug' => 'canned-processed-goods',     'description' => 'Sardines, tuna flakes, corned beef, spam, instant noodles, tomato sauce',    'sort_order' => 10],
            ['name' => 'Beverages',                     'slug' => 'beverages',                  'description' => '3-in-1 coffee, powdered juice, softdrinks, bottled water, energy drinks',   'sort_order' => 11],
            ['name' => 'Personal Care & Hygiene',       'slug' => 'personal-care-hygiene',      'description' => 'Soap, shampoo, toothpaste, detergent, dishwashing liquid, sanitary pads',   'sort_order' => 12],
            ['name' => 'Baby & Infant Needs',           'slug' => 'baby-infant-needs',          'description' => 'Powdered milk, diapers, baby powder, cotton balls',                          'sort_order' => 13],
            ['name' => 'Snacks & Confectionery',        'slug' => 'snacks-confectionery',       'description' => 'Biscuits, chips, candies, bread, crackers',                                  'sort_order' => 14],
            ['name' => 'General Merchandise',           'slug' => 'general-merchandise',        'description' => 'School supplies, matches, candles, batteries, rubber bands, notebooks',      'sort_order' => 15],
        ];

        foreach ($categories as $category) {
            Category::create([
                'uuid'        => Str::uuid(),
                'store_id'    => $store->id,
                'name'        => $category['name'],
                'slug'        => $category['slug'],
                'description' => $category['description'],
                'sort_order'  => $category['sort_order'],
                'is_active'   => true,
            ]);
        }
    }
}
