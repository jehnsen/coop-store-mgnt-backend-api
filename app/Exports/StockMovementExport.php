<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
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
            'Date',
            'Product SKU',
            'Product Name',
            'Branch',
            'Type',
            'Qty Before',
            'Qty Change',
            'Qty After',
            'Reason',
            'User',
        ];
    }

    public function map($row): array
    {
        return [
            $row['date'],
            $row['product']['sku'] ?? 'N/A',
            $row['product']['name'] ?? 'N/A',
            $row['branch']['name'] ?? 'N/A',
            ucfirst($row['type']),
            $row['quantity_before'],
            $row['quantity_change'],
            $row['quantity_after'],
            $row['reason'] ?? '',
            $row['user']['name'] ?? 'N/A',
        ];
    }

    public function title(): string
    {
        return 'Stock Movement';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
