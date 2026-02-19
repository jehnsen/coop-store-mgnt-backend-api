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
            'name' => 'JM Hardware & Construction Supply',
            'slug' => 'jm-hardware-construction-supply',
            'address' => '123 Commonwealth Avenue, Barangay Holy Spirit',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'phone' => '(02) 8123-4567',
            'email' => 'info@jmhardware.ph',
            'tin' => '123-456-789-000',
            'bir_permit_no' => 'FP-082-2024-0001234',
            'bir_min' => '123456789012345678',
            'receipt_header' => "JM HARDWARE & CONSTRUCTION SUPPLY\n123 Commonwealth Avenue, Brgy. Holy Spirit\nQuezon City, Metro Manila 1127\nTIN: 123-456-789-000\nBIR Permit No: FP-082-2024-0001234\nAccredited by BIR for use of POS Machine\nMin: 123456789012345678",
            'receipt_footer' => "Thank you for shopping at JM Hardware!\nFor inquiries and concerns:\nCall: (02) 8123-4567\nEmail: info@jmhardware.ph\nWebsite: www.jmhardware.ph\n\nTHIS SERVES AS YOUR OFFICIAL RECEIPT\nVAT INCLUSIVE SALE\nPlease keep this receipt for warranty claims",
            'vat_rate' => 12.00,
            'vat_inclusive' => true,
            'is_vat_registered' => true,
            'default_credit_terms_days' => 30,
            'default_credit_limit' => 5000000, // ₱50,000 in centavos
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'subscription_plan' => 'pro',
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
            'settings' => json_encode([
                'business_hours' => [
                    'monday' => ['open' => '07:00', 'close' => '18:00'],
                    'tuesday' => ['open' => '07:00', 'close' => '18:00'],
                    'wednesday' => ['open' => '07:00', 'close' => '18:00'],
                    'thursday' => ['open' => '07:00', 'close' => '18:00'],
                    'friday' => ['open' => '07:00', 'close' => '18:00'],
                    'saturday' => ['open' => '07:00', 'close' => '17:00'],
                    'sunday' => ['open' => '08:00', 'close' => '15:00'],
                ],
                'low_stock_threshold_percentage' => 20,
                'enable_sms_notifications' => true,
                'enable_email_notifications' => true,
                'auto_generate_sku' => true,
                'sku_prefix' => 'JMH',
                'invoice_prefix' => 'INV',
                'po_prefix' => 'PO',
                'delivery_prefix' => 'DEL',
                'default_discount_senior' => 5.00,
                'default_discount_pwd' => 5.00,
                'enable_loyalty_program' => false,
                'minimum_sale_amount' => 0,
                'enable_barcode_printing' => true,
                'receipt_printer_name' => 'EPSON TM-T82',
                'barcode_printer_name' => 'Zebra ZD220',
            ]),
        ]);
    }
}
