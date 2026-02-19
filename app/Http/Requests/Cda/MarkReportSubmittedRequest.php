<?php

namespace App\Http\Requests\Cda;

use Illuminate\Foundation\Http\FormRequest;

class MarkReportSubmittedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submitted_date'       => ['required', 'date', 'before_or_equal:today'],
            'submission_reference' => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }
}
