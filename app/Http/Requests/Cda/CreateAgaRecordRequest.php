<?php

namespace App\Http\Requests\Cda;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgaRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_type'       => ['required', 'in:annual,special'],
            'meeting_year'       => ['required', 'integer', 'min:2000', 'max:2100'],
            'meeting_date'       => ['required', 'date'],
            'venue'              => ['nullable', 'string', 'max:255'],
            'total_members'      => ['nullable', 'integer', 'min:0'],
            'members_present'    => ['nullable', 'integer', 'min:0'],
            'members_via_proxy'  => ['nullable', 'integer', 'min:0'],
            'quorum_achieved'    => ['nullable', 'boolean'],
            'presiding_officer'  => ['nullable', 'string', 'max:150'],
            'secretary'          => ['nullable', 'string', 'max:150'],
            'agenda'             => ['nullable', 'array'],
            'agenda.*'           => ['string'],
            'resolutions_passed' => ['nullable', 'array'],
            'resolutions_passed.*'=> ['string'],
            'minutes_text'       => ['nullable', 'string'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }
}
