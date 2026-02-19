<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeadStockExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'SKU',
            'Product Name',
            'Category',
            'Current Stock',
            'Cost Price (PHP)',
            'Stock Value (PHP)',
            'Days Since Last Sale',
            'Last Sale Date',
        ];
    }

    public function map($row): array
    {
        return [
            $row['sku'],
            $row['name'],
            $row['category'] ?? 'N/A',
            $row['current_stock'],
            number_format($row['cost_price'], 2),
            number_format($row['stock_value'], 2),
            $row['days_since_last_sale'] ?? 'Never',
            $row['last_sale_date'] ?? 'N/A',
        ];
    }

    public function title(): string
    {
        return 'Dead Stock';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
