<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchasesBySupplierExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Supplier Code',
            'Supplier Name',
            'Email',
            'Phone',
            'PO Count',
            'Total Amount (PHP)',
        ];
    }

    public function map($row): array
    {
        $supplier = $row['supplier'] ?? [];
        return [
            $supplier['code'] ?? 'N/A',
            $supplier['name'] ?? 'N/A',
            $supplier['email'] ?? 'N/A',
            $supplier['phone'] ?? 'N/A',
            $row['po_count'],
            number_format($row['total_amount'], 2),
        ];
    }

    public function title(): string
    {
        return 'Purchases by Supplier';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
