<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesSummaryExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Period',
            'Transaction Count',
            'Total Sales (PHP)',
            'Total Discounts (PHP)',
            'Average Transaction (PHP)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['period'],
            $row['transaction_count'],
            number_format($row['total_sales'], 2),
            number_format($row['total_discounts'], 2),
            number_format($row['average_transaction'], 2),
        ];
    }

    public function title(): string
    {
        return 'Sales Summary';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
