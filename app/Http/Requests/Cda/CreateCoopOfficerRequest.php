<?php

namespace App\Http\Requests\Cda;

use Illuminate\Foundation\Http\FormRequest;

class CreateCoopOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid' => ['nullable', 'string', 'exists:customers,uuid'],
            'name'          => ['required', 'string', 'max:150'],
            'position'      => ['required', 'string', 'max:100'],
            'committee'     => ['nullable', 'string', 'max:100'],
            'term_from'     => ['required', 'date'],
            'term_to'       => ['nullable', 'date', 'after_or_equal:term_from'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
