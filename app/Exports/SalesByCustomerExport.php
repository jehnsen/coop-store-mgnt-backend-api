<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByCustomerExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data['data']);
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'Customer Name',
            'Email',
            'Phone',
            'Transaction Count',
            'Total Purchases (PHP)',
            'Average Order Value (PHP)',
            'Last Purchase Date',
        ];
    }

    public function map($row): array
    {
        $customer = $row['customer'] ?? [];
        return [
            $customer['code'] ?? 'N/A',
            $customer['name'] ?? 'N/A',
            $customer['email'] ?? 'N/A',
            $customer['phone'] ?? 'N/A',
            $row['transaction_count'],
            number_format($row['total_purchases'], 2),
            number_format($row['average_order_value'], 2),
            $row['last_purchase_date'],
        ];
    }

    public function title(): string
    {
        return 'Sales by Customer';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
