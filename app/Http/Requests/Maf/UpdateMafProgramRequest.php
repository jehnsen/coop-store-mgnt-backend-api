<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMafProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                => ['sometimes', 'required', 'string', 'max:20'],
            'name'                => ['sometimes', 'required', 'string', 'max:100'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'benefit_type'        => ['sometimes', 'required', 'string', Rule::in([
                'death', 'hospitalization', 'disability', 'calamity', 'funeral',
            ])],
            'benefit_amount'      => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'waiting_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'max_claims_per_year' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->filled('code')) {
                return;
            }

            // Resolve the program being updated from the route
            $program = $this->route('program');
            $storeId = auth()->user()->store_id;
            $code    = $this->input('code');

            $exists = \App\Models\MafProgram::where('store_id', $storeId)
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->when($program, fn ($q) => $q->where('uuid', '!=', $program->uuid))
                ->exists();

            if ($exists) {
                $validator->errors()->add('code', "A MAF program with code \"{$code}\" already exists in this store.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'benefit_type.in'    => 'Benefit type must be one of: death, hospitalization, disability, calamity, funeral.',
            'benefit_amount.min' => 'Benefit amount must be at least ₱0.01.',
        ];
    }
}
