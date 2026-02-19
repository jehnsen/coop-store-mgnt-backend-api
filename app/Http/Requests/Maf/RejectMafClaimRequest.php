<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;

class RejectMafClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'A rejection reason is required.',
            'rejection_reason.min'      => 'Please provide a more detailed rejection reason (at least 10 characters).',
        ];
    }
}
