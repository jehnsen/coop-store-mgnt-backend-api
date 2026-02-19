<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LowStockExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Reorder Point',
            'Min Order Qty',
            'Unit',
            'Stock %',
            'Days Until Stockout',
            'Urgency',
        ];
    }

    public function map($row): array
    {
        return [
            $row['sku'],
            $row['name'],
            $row['category'] ?? 'N/A',
            $row['current_stock'],
            $row['reorder_point'],
            $row['minimum_order_qty'],
            $row['unit'] ?? '',
            $row['stock_percentage'] . '%',
            $row['estimated_days_until_stockout'] ?? 'N/A',
            ucfirst($row['urgency']),
        ];
    }

    public function title(): string
    {
        return 'Low Stock Alert';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
