<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByPaymentMethodExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Payment Method',
            'Transaction Count',
            'Total Amount (PHP)',
            'Percentage (%)',
        ];
    }

    public function map($row): array
    {
        return [
            ucfirst($row['method']),
            $row['transaction_count'],
            number_format($row['total_amount'], 2),
            $row['percentage'] . '%',
        ];
    }

    public function title(): string
    {
        return 'Sales by Payment Method';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
