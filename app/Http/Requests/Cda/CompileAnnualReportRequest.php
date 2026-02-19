<?php

namespace App\Http\Requests\Cda;

use Illuminate\Foundation\Http\FormRequest;

class CompileAnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_year'       => ['required', 'integer', 'min:2000', 'max:2100'],
            'cda_reg_number'    => ['nullable', 'string', 'max:50'],
            'cooperative_type'  => ['nullable', 'string', 'max:100'],
            'area_of_operation' => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
