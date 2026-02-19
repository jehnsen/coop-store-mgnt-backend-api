<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use App\Models\MafProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileMafClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maf_program_uuid'       => [
                'required',
                'string',
                Rule::exists('maf_programs', 'uuid')->where(fn ($q) =>
                    $q->where('store_id', auth()->user()->store_id)
                      ->where('is_active', true)
                      ->whereNull('deleted_at')
                ),
            ],
            'beneficiary_uuid'       => ['nullable', 'string'],
            'incident_date'          => ['required', 'date', 'before_or_equal:today'],
            'claim_date'             => ['nullable', 'date', 'before_or_equal:today'],
            'incident_description'   => ['required', 'string', 'min:10', 'max:5000'],
            'claimed_amount'         => ['required', 'numeric', 'min:0.01'],
            'supporting_documents'   => ['nullable', 'array'],
            'supporting_documents.*' => ['string', 'max:500'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate claimed_amount does not exceed program benefit_amount
            $programUuid = $this->input('maf_program_uuid');

            if ($programUuid && !$validator->errors()->has('maf_program_uuid')) {
                $program = MafProgram::where('uuid', $programUuid)->first();

                if ($program) {
                    $claimedCentavos = (int) round((float) $this->input('claimed_amount', 0) * 100);
                    $benefitCentavos = $program->getRawOriginal('benefit_amount');

                    if ($claimedCentavos > $benefitCentavos) {
                        $validator->errors()->add(
                            'claimed_amount',
                            sprintf(
                                'Claimed amount (₱%s) exceeds the program\'s maximum benefit of ₱%s.',
                                number_format($claimedCentavos / 100, 2),
                                number_format($benefitCentavos / 100, 2),
                            )
                        );
                    }
                }
            }

            // Incident date must not be in the future
            $incidentDate = $this->input('incident_date');
            if ($incidentDate && now()->startOfDay()->lt(\Carbon\Carbon::parse($incidentDate)->startOfDay())) {
                $validator->errors()->add('incident_date', 'Incident date cannot be in the future.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'maf_program_uuid.required' => 'Please select a MAF benefit program.',
            'maf_program_uuid.exists'   => 'The selected program does not exist or is inactive.',
            'incident_date.required'    => 'Incident date is required.',
            'incident_date.before_or_equal' => 'Incident date cannot be in the future.',
            'incident_description.required' => 'A description of the incident is required.',
            'incident_description.min'  => 'Please provide more detail about the incident (at least 10 characters).',
            'claimed_amount.required'   => 'Claimed amount is required.',
            'claimed_amount.min'        => 'Claimed amount must be at least ₱0.01.',
        ];
    }

    public function attributes(): array
    {
        return [
            'maf_program_uuid'     => 'benefit program',
            'beneficiary_uuid'     => 'beneficiary',
            'incident_date'        => 'incident date',
            'incident_description' => 'incident description',
            'claimed_amount'       => 'claimed amount',
        ];
    }
}
