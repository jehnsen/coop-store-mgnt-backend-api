<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductProfitabilityExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Quantity Sold',
            'Total Revenue (PHP)',
            'Total Cost (PHP)',
            'Gross Profit (PHP)',
            'Margin %',
        ];
    }

    public function map($row): array
    {
        return [
            $row['product_sku'],
            $row['product_name'],
            $row['quantity_sold'],
            number_format($row['total_revenue'], 2),
            number_format($row['total_cost'], 2),
            number_format($row['gross_profit'], 2),
            $row['margin_percentage'] . '%',
        ];
    }

    public function title(): string
    {
        return 'Product Profitability';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
