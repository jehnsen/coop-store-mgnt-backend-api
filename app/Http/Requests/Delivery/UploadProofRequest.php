<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadProofRequest extends FormRequest
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
            'proof_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB
            'signature_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 2MB
            'received_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateDeliveryStatus($validator);
        });
    }

    /**
     * Validate that proof of delivery can only be uploaded for delivered status.
     */
    protected function validateDeliveryStatus(Validator $validator): void
    {
        $delivery = $this->route('delivery');

        if (!$delivery) {
            return;
        }

        if ($delivery->status !== 'delivered') {
            $validator->errors()->add(
                'status',
                "Proof of delivery can only be uploaded when the delivery status is 'delivered'. Current status: {$delivery->status}."
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'proof_image.required' => 'The proof of delivery image is required.',
            'proof_image.image' => 'The proof file must be an image.',
            'proof_image.mimes' => 'The proof image must be a JPEG, PNG, or JPG file.',
            'proof_image.max' => 'The proof image must not exceed 5MB.',
            'signature_image.image' => 'The signature file must be an image.',
            'signature_image.mimes' => 'The signature image must be a JPEG, PNG, or JPG file.',
            'signature_image.max' => 'The signature image must not exceed 2MB.',
            'received_by.max' => 'The received by field must not exceed 255 characters.',
            'notes.max' => 'The notes must not exceed 500 characters.',
        ];
    }
}
