<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByCategoryExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Category',
            'Total Quantity',
            'Total Sales (PHP)',
            'Transaction Count',
            'Percentage (%)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['category_name'],
            $row['total_quantity'],
            number_format($row['total_sales'], 2),
            $row['transaction_count'],
            $row['percentage'] . '%',
        ];
    }

    public function title(): string
    {
        return 'Sales by Category';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
