<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();

        // ── Customers / Members ───────────────────────────────────────────────
        // Realistic rural Nueva Ecija members: farmers, small traders, fisherfolk,
        // livestock raisers, and a few barangay-level government accounts.
        // credit_limit & total_outstanding are in CENTAVOS.

        $customers = [

            // ─── Generic walk-in account ─────────────────────────────────────
            [
                'code'              => 'WALK-IN-001',
                'name'              => 'Walk-in Customer',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => null,
                'address'           => 'N/A',
                'city'              => null,
                'province'          => null,
                'postal_code'       => null,
                'member_status'     => null,
                'credit_limit'      => 0,
                'credit_terms_days' => 0,
                'total_outstanding' => 0,
                'total_purchases'   => 142500,  // ₱1,425
                'payment_rating'    => 'excellent',
                'notes'             => 'Generic walk-in account for non-member cash sales.',
                'is_active'         => true,
                'allow_credit'      => false,
            ],

            // ─── Active members – rice/corn farmers ──────────────────────────
            [
                'code'              => 'MBR-001',
                'name'              => 'Bernardo Macaraeg',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0917-234-5678',
                'address'           => 'Purok 2, Barangay Rizal',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 500000,  // ₱5,000
                'credit_terms_days' => 90,       // planting-season terms
                'total_outstanding' => 280000,  // ₱2,800
                'total_purchases'   => 865000,  // ₱8,650
                'payment_rating'    => 'good',
                'notes'             => 'Rice farmer, 3 ha. Purchases seeds, urea, and herbicide every cropping season. Pays after harvest.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],
            [
                'code'              => 'MBR-002',
                'name'              => 'Erlinda Pascual',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0918-345-6789',
                'address'           => 'Purok 4, Barangay San Roque',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 500000,  // ₱5,000
                'credit_terms_days' => 90,
                'total_outstanding' => 0,
                'total_purchases'   => 1245000, // ₱12,450
                'payment_rating'    => 'excellent',
                'notes'             => 'Rice farmer, 1.5 ha. Consistent buyer of complete fertilizer and certified seeds. Excellent payer.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],
            [
                'code'              => 'MBR-003',
                'name'              => 'Rodrigo Tolentino',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0919-456-7890',
                'address'           => 'Purok 1, Barangay Tagumpay',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 750000,  // ₱7,500
                'credit_terms_days' => 90,
                'total_outstanding' => 520000,  // ₱5,200
                'total_purchases'   => 2180000, // ₱21,800
                'payment_rating'    => 'good',
                'notes'             => 'Corn-rice farmer, 5 ha. Buys bulk fertilizer and pesticide. Has existing crop-loan with the coop.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],
            [
                'code'              => 'MBR-004',
                'name'              => 'Natividad Buenaventura',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0920-567-8901',
                'address'           => 'Purok 3, Barangay Mabini',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 300000,  // ₱3,000
                'credit_terms_days' => 60,
                'total_outstanding' => 0,
                'total_purchases'   => 540000,  // ₱5,400
                'payment_rating'    => 'excellent',
                'notes'             => 'Vegetable farmer (pechay, sitaw). Regular buyer of veggie seeds and foliar fertilizer.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],

            // ─── Active members – livestock / poultry ─────────────────────────
            [
                'code'              => 'MBR-005',
                'name'              => 'Aurelio Dizon',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => '(044) 940-3310',
                'mobile'            => '0921-678-9012',
                'address'           => 'Purok 5, Barangay Caalibangbangan',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 1000000, // ₱10,000
                'credit_terms_days' => 30,
                'total_outstanding' => 380000,  // ₱3,800
                'total_purchases'   => 3860000, // ₱38,600
                'payment_rating'    => 'good',
                'notes'             => 'Backyard poultry raiser (500 broilers/batch). Buys starter feeds and veterinary supplies monthly.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],
            [
                'code'              => 'MBR-006',
                'name'              => 'Corazon Valdez',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0922-789-0123',
                'address'           => 'Purok 2, Barangay San Jose',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 750000,  // ₱7,500
                'credit_terms_days' => 30,
                'total_outstanding' => 0,
                'total_purchases'   => 2140000, // ₱21,400
                'payment_rating'    => 'excellent',
                'notes'             => 'Hog raiser (20 heads). Regular buyer of hog grower feeds and dewormer tablets. Always pays on time.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],

            // ─── Active members – fisherfolk / aquaculture ────────────────────
            [
                'code'              => 'MBR-007',
                'name'              => 'Feliciano Bautista',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0923-890-1234',
                'address'           => 'Sitio Palaisdaan, Barangay Pantoc',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 500000,  // ₱5,000
                'credit_terms_days' => 60,
                'total_outstanding' => 162000,  // ₱1,620
                'total_purchases'   => 980000,  // ₱9,800
                'payment_rating'    => 'good',
                'notes'             => 'Tilapia fish pond operator (0.5 ha). Buys tilapia feeds, fishing nets, and PP sacks for harvest.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],

            // ─── Active members – sari-sari / small traders ───────────────────
            [
                'code'              => 'MBR-008',
                'name'              => 'Merced Domingo',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0924-901-2345',
                'address'           => 'Purok 1, Barangay Poblacion',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 300000,  // ₱3,000
                'credit_terms_days' => 15,
                'total_outstanding' => 0,
                'total_purchases'   => 685000,  // ₱6,850
                'payment_rating'    => 'excellent',
                'notes'             => 'Sari-sari store owner. Buys canned goods, beverages, personal care items, and general merchandise for resale.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],
            [
                'code'              => 'MBR-009',
                'name'              => 'Renato Ramos',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0925-012-3456',
                'address'           => 'Purok 3, Barangay Tagumpay',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'regular',
                'credit_limit'      => 500000,  // ₱5,000
                'credit_terms_days' => 30,
                'total_outstanding' => 215000,  // ₱2,150
                'total_purchases'   => 1450000, // ₱14,500
                'payment_rating'    => 'good',
                'notes'             => 'Small rice trader and rice retailer. Buys rice in bulk (Sinandomeng) for resale in the palengke.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],

            // ─── Inactive / overdue member ────────────────────────────────────
            [
                'code'              => 'MBR-010',
                'name'              => 'Honorio Pagtalunan',
                'type'              => 'individual',
                'company_name'      => null,
                'tin'               => null,
                'email'             => null,
                'phone'             => null,
                'mobile'            => '0926-123-4567',
                'address'           => 'Purok 6, Barangay San Roque',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => 'inactive',
                'credit_limit'      => 500000,  // ₱5,000
                'credit_terms_days' => 90,
                'total_outstanding' => 485000,  // ₱4,850
                'total_purchases'   => 1285000, // ₱12,850
                'payment_rating'    => 'poor',
                'notes'             => 'Rice farmer. Outstanding balance from last cropping season. Account frozen pending settlement. Pending reinstatement.',
                'is_active'         => false,
                'allow_credit'      => false,
            ],

            // ─── Government / Barangay account ───────────────────────────────
            [
                'code'              => 'GOV-001',
                'name'              => 'Barangay Poblacion LGU – San Isidro',
                'type'              => 'business',
                'company_name'      => 'Barangay Government of Poblacion, San Isidro',
                'tin'               => '000-111-222-000',
                'email'             => 'brgy.poblacion.sanisidro@gmail.com',
                'phone'             => '(044) 940-0001',
                'mobile'            => '0917-100-2233',
                'address'           => 'Barangay Hall, Poblacion',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => null,
                'credit_limit'      => 2000000, // ₱20,000
                'credit_terms_days' => 60,
                'total_outstanding' => 0,
                'total_purchases'   => 1850000, // ₱18,500
                'payment_rating'    => 'good',
                'notes'             => 'Purchases agri inputs and grocery items for Barangay Maisug (Gulayan sa Barangay) and community feeding programs. Payment via ADA/checks.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],

            // ─── Large trader / regular wholesale buyer ───────────────────────
            [
                'code'              => 'TRD-001',
                'name'              => 'Magtanggol Fertilizer & Trading',
                'type'              => 'business',
                'company_name'      => 'Magtanggol Fertilizer & Trading',
                'tin'               => '456-321-654-000',
                'email'             => 'magtanggol.trading@gmail.com',
                'phone'             => '(044) 600-8888',
                'mobile'            => '0918-999-1234',
                'address'           => 'Maharlika Highway, Brgy. Bagong Sikat',
                'city'              => 'San Isidro',
                'province'          => 'Nueva Ecija',
                'postal_code'       => '3112',
                'member_status'     => null,
                'credit_limit'      => 5000000, // ₱50,000
                'credit_terms_days' => 30,
                'total_outstanding' => 1250000, // ₱12,500
                'total_purchases'   => 12500000, // ₱125,000
                'payment_rating'    => 'excellent',
                'notes'             => 'Local agri trading business. Buys fertilizer, pesticides, and seeds in bulk for resale to nearby barangays. Wholesale pricing applies.',
                'is_active'         => true,
                'allow_credit'      => true,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create([
                'uuid'               => Str::uuid(),
                'store_id'           => $store->id,
                'code'               => $customer['code'],
                'name'               => $customer['name'],
                'type'               => $customer['type'],
                'company_name'       => $customer['company_name'],
                'tin'                => $customer['tin'],
                'email'              => $customer['email'],
                'phone'              => $customer['phone'],
                'mobile'             => $customer['mobile'],
                'address'            => $customer['address'],
                'city'               => $customer['city'],
                'province'           => $customer['province'],
                'postal_code'        => $customer['postal_code'],
                'member_status'      => $customer['member_status'],
                'credit_limit'       => $customer['credit_limit'],
                'credit_terms_days'  => $customer['credit_terms_days'],
                'total_outstanding'  => $customer['total_outstanding'],
                'total_purchases'    => $customer['total_purchases'],
                'payment_rating'     => $customer['payment_rating'],
                'notes'              => $customer['notes'],
                'is_active'          => $customer['is_active'],
                'allow_credit'       => $customer['allow_credit'],
            ]);
        }
    }
}
