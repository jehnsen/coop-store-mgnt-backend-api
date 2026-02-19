<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CreditAgingExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data['customers']);
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'Customer Name',
            'Total Outstanding (PHP)',
            'Current (PHP)',
            '31-60 Days (PHP)',
            '61-90 Days (PHP)',
            'Over 90 Days (PHP)',
            'Oldest Invoice Days',
        ];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            number_format($row->total_outstanding, 2),
            number_format($row->aging_current ?? 0, 2),
            number_format($row->aging_31_60 ?? 0, 2),
            number_format($row->aging_61_90 ?? 0, 2),
            number_format($row->aging_over_90 ?? 0, 2),
            $row->oldest_invoice_days ?? 0,
        ];
    }

    public function title(): string
    {
        return 'Credit Aging';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
