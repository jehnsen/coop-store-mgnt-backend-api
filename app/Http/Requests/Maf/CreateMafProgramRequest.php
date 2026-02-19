<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMafProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                => ['required', 'string', 'max:20'],
            'name'                => ['required', 'string', 'max:100'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'benefit_type'        => ['required', 'string', Rule::in([
                'death', 'hospitalization', 'disability', 'calamity', 'funeral',
            ])],
            'benefit_amount'      => ['required', 'numeric', 'min:0.01'],
            'waiting_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'max_claims_per_year' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $code    = $this->input('code');
            $storeId = auth()->user()->store_id;

            $exists = \App\Models\MafProgram::where('store_id', $storeId)
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $validator->errors()->add('code', "A MAF program with code \"{$code}\" already exists in this store.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required'           => 'A unique program code is required (e.g. DEATH-01).',
            'code.max'                => 'Code may not exceed 20 characters.',
            'name.required'           => 'Program name is required.',
            'benefit_type.required'   => 'Benefit type is required.',
            'benefit_type.in'         => 'Benefit type must be one of: death, hospitalization, disability, calamity, funeral.',
            'benefit_amount.required' => 'Benefit amount is required.',
            'benefit_amount.min'      => 'Benefit amount must be at least ₱0.01.',
            'waiting_period_days.min' => 'Waiting period cannot be negative.',
            'max_claims_per_year.min' => 'Maximum claims per year must be at least 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'benefit_amount'      => 'benefit amount',
            'waiting_period_days' => 'waiting period (days)',
            'max_claims_per_year' => 'max claims per year',
        ];
    }
}
