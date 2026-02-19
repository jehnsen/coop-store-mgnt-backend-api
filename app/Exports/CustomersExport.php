<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomersExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $customers;

    public function __construct($customers)
    {
        $this->customers = $customers;
    }

    public function collection()
    {
        return $this->customers;
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'Name',
            'Type',
            'Company Name',
            'TIN',
            'Email',
            'Phone',
            'Mobile',
            'Address',
            'City',
            'Province',
            'Postal Code',
            'Credit Limit (₱)',
            'Credit Terms (Days)',
            'Total Outstanding (₱)',
            'Available Credit (₱)',
            'Total Purchases (₱)',
            'Payment Rating',
            'Customer Tier',
            'Status',
            'Allow Credit',
            'Notes',
            'Created Date',
        ];
    }

    public function map($customer): array
    {
        // Calculate available credit
        $availableCredit = max(0, $customer->credit_limit - $customer->total_outstanding);

        // Determine customer tier based on total purchases
        $tier = $this->getCustomerTier($customer->total_purchases);

        return [
            $customer->code,
            $customer->name,
            ucfirst(str_replace('_', ' ', $customer->type)),
            $customer->company_name ?? '',
            $customer->tin ?? '',
            $customer->email ?? '',
            $customer->phone ?? '',
            $customer->mobile ?? '',
            $customer->address ?? '',
            $customer->city ?? '',
            $customer->province ?? '',
            $customer->postal_code ?? '',
            number_format($customer->credit_limit, 2),
            $customer->credit_terms_days,
            number_format($customer->total_outstanding, 2),
            number_format($availableCredit, 2),
            number_format($customer->total_purchases, 2),
            $customer->payment_rating ?? 'N/A',
            $tier,
            $customer->is_active ? 'Active' : 'Inactive',
            $customer->allow_credit ? 'Yes' : 'No',
            $customer->notes ?? '',
            $customer->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    public function title(): string
    {
        return 'Customers';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Calculate customer tier based on total purchases.
     */
    private function getCustomerTier(float $totalPurchases): string
    {
        if ($totalPurchases >= 1000001) {
            return 'Platinum';
        } elseif ($totalPurchases >= 500001) {
            return 'Gold';
        } elseif ($totalPurchases >= 100001) {
            return 'Silver';
        }

        return 'Bronze';
    }
}
