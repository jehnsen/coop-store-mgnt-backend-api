<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailySalesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data['hourly_breakdown']);
    }

    public function headings(): array
    {
        return [
            'Hour',
            'Transaction Count',
            'Total Sales (PHP)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['hour'] . ':00',
            $row['transaction_count'],
            number_format($row['total_sales'], 2),
        ];
    }

    public function title(): string
    {
        return 'Daily Sales';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
