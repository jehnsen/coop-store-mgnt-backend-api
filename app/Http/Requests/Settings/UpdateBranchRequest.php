<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'owner' || $this->user()->role === 'manager';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Get the branch being updated
        $branchUuid = $this->route('uuid');
        $branch = \App\Models\Branch::where('uuid', $branchUuid)
            ->where('store_id', $this->user()->store_id)
            ->first();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'unique:branches,name,' . ($branch ? $branch->id : 'NULL') . ',id,store_id,' . $this->user()->store_id
            ],
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Branch name is required',
            'name.unique' => 'A branch with this name already exists',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->has('is_main')) {
            $mergeData['is_main'] = filter_var($this->is_main, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('is_active')) {
            $mergeData['is_active'] = filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }
}
