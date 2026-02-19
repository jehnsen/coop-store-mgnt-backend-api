<?php

namespace App\Http\Requests\PatronageRefund;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePatronageRefundBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
