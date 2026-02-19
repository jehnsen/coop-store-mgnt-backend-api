<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;

class ReverseMafContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reversal_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reversal_reason.required' => 'A reason for reversing this contribution is required.',
            'reversal_reason.min'      => 'Please provide a more descriptive reason (at least 5 characters).',
        ];
    }
}
