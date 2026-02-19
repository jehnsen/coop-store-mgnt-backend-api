<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:preparing,dispatched,in_transit,delivered,failed',
            'notes' => 'nullable|string|max:500',
            'delivered_at' => 'nullable|date',
            'failed_reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateStatusTransition($validator);
            $this->validateRequiredFieldsForStatus($validator);
        });
    }

    /**
     * Validate that the status transition is allowed.
     */
    protected function validateStatusTransition(Validator $validator): void
    {
        $delivery = $this->route('delivery');

        if (!$delivery) {
            return;
        }

        $currentStatus = $delivery->status;
        $newStatus = $this->status;

        // Define valid status transitions
        $validTransitions = [
            'preparing' => ['dispatched', 'failed'],
            'dispatched' => ['in_transit', 'delivered', 'failed'],
            'in_transit' => ['delivered', 'failed'],
            'delivered' => [], // Delivered is a final state
            'failed' => [], // Failed is a final state
        ];

        // Check if transition is valid
        if (!isset($validTransitions[$currentStatus])) {
            $validator->errors()->add('status', "Invalid current status: {$currentStatus}.");
            return;
        }

        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            $validator->errors()->add(
                'status',
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'. Valid transitions: " .
                (empty($validTransitions[$currentStatus]) ? 'none (final state)' : implode(', ', $validTransitions[$currentStatus]))
            );
        }
    }

    /**
     * Validate required fields based on the status.
     */
    protected function validateRequiredFieldsForStatus(Validator $validator): void
    {
        // If status is 'delivered', delivered_at should be provided
        if ($this->status === 'delivered' && !$this->delivered_at) {
            $validator->errors()->add('delivered_at', 'The delivered at field is required when status is delivered.');
        }

        // If status is 'failed', failed_reason should be provided
        if ($this->status === 'failed' && !$this->failed_reason) {
            $validator->errors()->add('failed_reason', 'The failed reason field is required when status is failed.');
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'The status is required.',
            'status.in' => 'The selected status is invalid.',
            'delivered_at.date' => 'The delivered at must be a valid date.',
            'failed_reason.max' => 'The failed reason must not exceed 500 characters.',
        ];
    }
}
