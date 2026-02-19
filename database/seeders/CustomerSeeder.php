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

        $customers = [
            // Walk-in customers (no credit)
            [
                'code' => 'WALK-IN-001',
                'name' => 'Walk-in Customer',
                'type' => 'individual',
                'company_name' => null,
                'tin' => null,
                'email' => null,
                'phone' => null,
                'mobile' => null,
                'address' => 'N/A',
                'city' => null,
                'province' => null,
                'postal_code' => null,
                'credit_limit' => 0,
                'credit_terms_days' => 0,
                'total_outstanding' => 0,
                'total_purchases' => 285000, // ₱2,850
                'payment_rating' => 'excellent',
                'notes' => 'General walk-in customer account',
                'is_active' => true,
                'allow_credit' => false,
            ],
            [
                'code' => 'CUST-001',
                'name' => 'Roberto Villanueva',
                'type' => 'individual',
                'company_name' => null,
                'tin' => null,
                'email' => 'roberto.v@gmail.com',
                'phone' => null,
                'mobile' => '0917-123-4567',
                'address' => '45 Mariposa Street, Brgy. San Roque',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'postal_code' => '1109',
                'credit_limit' => 0,
                'credit_terms_days' => 0,
                'total_outstanding' => 0,
                'total_purchases' => 125000, // ₱1,250
                'payment_rating' => 'excellent',
                'notes' => 'Regular customer, prefers cash',
                'is_active' => true,
                'allow_credit' => false,
            ],

            // Regular customers (small credit limits)
            [
                'code' => 'CUST-002',
                'name' => 'Maria Lourdes Santos',
                'type' => 'individual',
                'company_name' => null,
                'tin' => null,
                'email' => 'mlsantos@yahoo.com',
                'phone' => null,
                'mobile' => '0918-234-5678',
                'address' => '78 Luna Street, Brgy. Mariana',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'postal_code' => '1112',
                'credit_limit' => 1000000, // ₱10,000
                'credit_terms_days' => 15,
                'total_outstanding' => 0,
                'total_purchases' => 345000, // ₱3,450
                'payment_rating' => 'excellent',
                'notes' => 'Small hardware store owner, always pays on time',
                'is_active' => true,
                'allow_credit' => true,
            ],
            [
                'code' => 'CUST-003',
                'name' => 'Pedro Alvarez',
                'type' => 'individual',
                'company_name' => null,
                'tin' => null,
                'email' => 'pedro.alvarez@gmail.com',
                'phone' => '(02) 8456-7890',
                'mobile' => '0919-345-6789',
                'address' => '123 J.P. Rizal Avenue, Brgy. San Isidro',
                'city' => 'Marikina City',
                'province' => 'Metro Manila',
                'postal_code' => '1800',
                'credit_limit' => 1500000, // ₱15,000
                'credit_terms_days' => 30,
                'total_outstanding' => 245000, // ₱2,450
                'total_purchases' => 1280000, // ₱12,800
                'payment_rating' => 'good',
                'notes' => 'Regular customer, sometimes pays late',
                'is_active' => true,
                'allow_credit' => true,
            ],
            [
                'code' => 'CUST-004',
                'name' => 'Jose Ramirez',
                'type' => 'individual',
                'company_name' => null,
                'tin' => null,
                'email' => 'jose.ram@hotmail.com',
                'phone' => null,
                'mobile' => '0920-456-7890',
                'address' => '56 Del Pilar Street, Brgy. Libis',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'postal_code' => '1110',
                'credit_limit' => 2500000, // ₱25,000
                'credit_terms_days' => 30,
                'total_outstanding' => 0,
                'total_purchases' => 785000, // ₱7,850
                'payment_rating' => 'excellent',
                'notes' => 'Reliable customer',
                'is_active' => true,
                'allow_credit' => true,
            ],

            // Contractor customers (large credit limits)
            [
                'code' => 'CONT-001',
                'name' => 'Antonio Reyes',
                'type' => 'business',
                'company_name' => 'Reyes Construction Services',
                'tin' => '123-456-789-000',
                'email' => 'ar.construction@gmail.com',
                'phone' => '(02) 8567-8901',
                'mobile' => '0921-567-8901',
                'address' => '234 Commonwealth Avenue, Brgy. Batasan Hills',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'postal_code' => '1126',
                'credit_limit' => 10000000, // ₱100,000
                'credit_terms_days' => 45,
                'total_outstanding' => 3750000, // ₱37,500
                'total_purchases' => 24500000, // ₱245,000
                'payment_rating' => 'excellent',
                'notes' => 'Trusted contractor, large volume orders',
                'is_active' => true,
                'allow_credit' => true,
            ],
            [
                'code' => 'CONT-002',
                'name' => 'Carmen Dela Cruz',
                'type' => 'business',
                'company_name' => 'CDC Builders Inc.',
                'tin' => '234-567-890-000',
                'email' => 'info@cdcbuilders.ph',
                'phone' => '(02) 8678-9012',
                'mobile' => '0922-678-9012',
                'address' => '789 Katipunan Avenue, Brgy. Blue Ridge',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'postal_code' => '1121',
                'credit_limit' => 30000000, // ₱300,000
                'credit_terms_days' => 60,
                'total_outstanding' => 12850000, // ₱128,500
                'total_purchases' => 58900000, // ₱589,000
                'payment_rating' => 'good',
                'notes' => 'Medium-sized contractor, occasional late payments',
                'is_active' => true,
                'allow_credit' => true,
            ],
            [
                'code' => 'CONT-003',
                'name' => 'Ricardo Bautista',
                'type' => 'business',
                'company_name' => 'RB Construction & Development',
                'tin' => '345-678-901-000',
                'email' => 'rbautista@rbconstruction.ph',
                'phone' => '(02) 8789-0123',
                'mobile' => '0923-789-0123',
                'address' => '456 Marcos Highway, Brgy. Mayamot',
                'city' => 'Antipolo City',
                'province' => 'Rizal',
                'postal_code' => '1870',
                'credit_limit' => 50000000, // ₱500,000
                'credit_terms_days' => 60,
                'total_outstanding' => 28950000, // ₱289,500
                'total_purchases' => 127500000, // ₱1,275,000
                'payment_rating' => 'good',
                'notes' => 'Large contractor, bulk orders, negotiates prices',
                'is_active' => true,
                'allow_credit' => true,
            ],

            // Government/Corporate (very large credit limits)
            [
                'code' => 'CORP-001',
                'name' => 'Department of Public Works and Highways',
                'type' => 'business',
                'company_name' => 'DPWH - NCR 1st District',
                'tin' => '000-999-888-000',
                'email' => 'procurement.ncr1@dpwh.gov.ph',
                'phone' => '(02) 8890-1234',
                'mobile' => null,
                'address' => 'DPWH Building, Quezon Avenue',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'postal_code' => '1100',
                'credit_limit' => 100000000, // ₱1,000,000
                'credit_terms_days' => 90,
                'total_outstanding' => 45600000, // ₱456,000
                'total_purchases' => 325000000, // ₱3,250,000
                'payment_rating' => 'good',
                'notes' => 'Government agency, payment takes time but guaranteed',
                'is_active' => true,
                'allow_credit' => true,
            ],
            [
                'code' => 'CORP-002',
                'name' => 'SM Prime Holdings',
                'type' => 'business',
                'company_name' => 'SM Prime Holdings Inc.',
                'tin' => '000-888-777-000',
                'email' => 'procurement@smprime.com',
                'phone' => '(02) 8857-0100',
                'mobile' => '0925-901-2345',
                'address' => 'Mall of Asia Complex',
                'city' => 'Pasay City',
                'province' => 'Metro Manila',
                'postal_code' => '1300',
                'credit_limit' => 100000000, // ₱1,000,000
                'credit_terms_days' => 60,
                'total_outstanding' => 0,
                'total_purchases' => 485000000, // ₱4,850,000
                'payment_rating' => 'excellent',
                'notes' => 'Corporate account, large orders, prompt payment',
                'is_active' => true,
                'allow_credit' => true,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'code' => $customer['code'],
                'name' => $customer['name'],
                'type' => $customer['type'],
                'company_name' => $customer['company_name'],
                'tin' => $customer['tin'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'mobile' => $customer['mobile'],
                'address' => $customer['address'],
                'city' => $customer['city'],
                'province' => $customer['province'],
                'postal_code' => $customer['postal_code'],
                'credit_limit' => $customer['credit_limit'],
                'credit_terms_days' => $customer['credit_terms_days'],
                'total_outstanding' => $customer['total_outstanding'],
                'total_purchases' => $customer['total_purchases'],
                'payment_rating' => $customer['payment_rating'],
                'notes' => $customer['notes'],
                'is_active' => $customer['is_active'],
                'allow_credit' => $customer['allow_credit'],
            ]);
        }
    }
}
