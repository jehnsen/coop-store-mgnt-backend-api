<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CollectionReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data['daily_collections']);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Payment Method',
            'Payment Count',
            'Total Collected (PHP)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['date'],
            ucfirst($row['payment_method'] ?? 'N/A'),
            $row['payment_count'],
            number_format($row['total_collected'], 2),
        ];
    }

    public function title(): string
    {
        return 'Collection Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
