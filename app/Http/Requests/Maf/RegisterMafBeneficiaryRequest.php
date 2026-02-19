<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMafBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:150'],
            'relationship'   => ['required', 'string', Rule::in([
                'spouse', 'child', 'parent', 'sibling', 'other',
            ])],
            'birth_date'     => ['nullable', 'date', 'before:today'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'is_primary'     => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'Beneficiary name is required.',
            'relationship.required' => 'Relationship to member is required.',
            'relationship.in'       => 'Relationship must be one of: spouse, child, parent, sibling, other.',
            'birth_date.before'     => 'Birth date must be in the past.',
        ];
    }
}
