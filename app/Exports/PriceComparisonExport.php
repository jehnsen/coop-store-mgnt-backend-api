<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PriceComparisonExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data['data'] as $product) {
            foreach ($product['suppliers'] as $supplier) {
                $rows[] = [
                    $product['product']['sku'],
                    $product['product']['name'],
                    number_format($product['product']['current_cost_price'], 2),
                    $supplier['supplier_code'],
                    $supplier['supplier_name'],
                    $supplier['supplier_sku'] ?? 'N/A',
                    number_format($supplier['supplier_price'], 2),
                    $supplier['lead_time_days'] ?? 'N/A',
                    $supplier['minimum_order_qty'] ?? 'N/A',
                    $supplier['is_preferred'] ? 'Yes' : 'No',
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Product SKU',
            'Product Name',
            'Current Cost (PHP)',
            'Supplier Code',
            'Supplier Name',
            'Supplier SKU',
            'Supplier Price (PHP)',
            'Lead Time (Days)',
            'Min Order Qty',
            'Preferred',
        ];
    }

    public function title(): string
    {
        return 'Price Comparison';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
