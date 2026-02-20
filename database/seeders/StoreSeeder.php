<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::create([
            'uuid' => Str::uuid(),
            'name' => 'Samahang Nayon ng Lungsod ng San Isidro Multi-Purpose Cooperative',
            'slug' => 'snlsi-mpc',
            'address' => 'Purok 3, Barangay Poblacion',
            'city' => 'San Isidro',
            'province' => 'Nueva Ecija',
            'phone' => '(044) 940-1234',
            'email' => 'snlsimpc@gmail.com',
            'tin' => '457-123-086-000',
            'bir_permit_no' => 'FP-040-2024-0005672',
            'bir_min' => '457123086000001',
            'receipt_header' => "SNLSI MULTI-PURPOSE COOPERATIVE\nPurok 3, Barangay Poblacion, San Isidro\nNueva Ecija 3112\nTIN: 457-123-086-000\nBIR Permit No: FP-040-2024-0005672\nCDA Reg. No: 9520-0311210\nMin: 457123086000001",
            'receipt_footer' => "Salamat sa inyong suporta sa aming kooperatiba!\nPara sa katanungan:\nTawagan: (044) 940-1234\nEmail: snlsimpc@gmail.com\n\nITO ANG INYONG OPISYAL NA RESIBO\nSINASAMA ANG BUWIS (VAT INCLUSIVE)\nMangyaring itago ang resibong ito",
            'vat_rate' => 12.00,
            'vat_inclusive' => true,
            'is_vat_registered' => true,
            'default_credit_terms_days' => 30,
            'default_credit_limit' => 500000, // ₱5,000 in centavos — typical rural coop member limit
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'subscription_plan' => 'pro',
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
            'settings' => json_encode([
                'business_hours' => [
                    'monday'    => ['open' => '07:00', 'close' => '17:00'],
                    'tuesday'   => ['open' => '07:00', 'close' => '17:00'],
                    'wednesday' => ['open' => '07:00', 'close' => '17:00'],
                    'thursday'  => ['open' => '07:00', 'close' => '17:00'],
                    'friday'    => ['open' => '07:00', 'close' => '17:00'],
                    'saturday'  => ['open' => '07:00', 'close' => '16:00'],
                    'sunday'    => ['open' => '08:00', 'close' => '12:00'],
                ],
                'low_stock_threshold_percentage' => 20,
                'enable_sms_notifications' => true,
                'enable_email_notifications' => false,
                'auto_generate_sku' => true,
                'sku_prefix' => 'SNLSI',
                'invoice_prefix' => 'INV',
                'po_prefix' => 'PO',
                'delivery_prefix' => 'DEL',
                'default_discount_senior' => 20.00,   // OSCA / PWD mandatory
                'default_discount_pwd' => 20.00,
                'enable_loyalty_program' => false,
                'minimum_sale_amount' => 0,
                'enable_barcode_printing' => true,
                'receipt_printer_name' => 'EPSON TM-T82',
                'barcode_printer_name' => 'Zebra ZD220',
                'cda_reg_no' => '9520-0311210',
                'cda_reg_date' => '2003-11-21',
                'cooperative_type' => 'multi_purpose',
                'fiscal_year_end' => '12-31',
                'par_value_per_share' => 10000,  // ₱100 in centavos
                'authorized_share_capital' => 500000000, // ₱5,000,000 in centavos
            ]),
        ]);
    }
}
