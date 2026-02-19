<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByCashierExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Cashier Name',
            'Email',
            'Transaction Count',
            'Total Sales (PHP)',
            'Average Transaction (PHP)',
        ];
    }

    public function map($row): array
    {
        $cashier = $row['cashier'] ?? [];
        return [
            $cashier['name'] ?? 'N/A',
            $cashier['email'] ?? 'N/A',
            $row['transaction_count'],
            number_format($row['total_sales'], 2),
            number_format($row['average_transaction'], 2),
        ];
    }

    public function title(): string
    {
        return 'Sales by Cashier';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
