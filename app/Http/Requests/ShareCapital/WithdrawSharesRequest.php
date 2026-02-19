<?php

namespace App\Http\Requests\ShareCapital;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawSharesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'withdrawn_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
