<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CdaAnnualReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                 => $this->uuid,
            'report_year'          => $this->report_year,
            'period_from'          => $this->period_from?->toDateString(),
            'period_to'            => $this->period_to?->toDateString(),
            'cda_reg_number'       => $this->cda_reg_number,
            'cooperative_type'     => $this->cooperative_type,
            'area_of_operation'    => $this->area_of_operation,
            'status'               => $this->status,
            'report_data'          => $this->report_data,
            'compiled_by'          => $this->whenLoaded('compiledBy', fn () => $this->compiledBy?->name),
            'compiled_at'          => $this->compiled_at?->toISOString(),
            'finalized_by'         => $this->whenLoaded('finalizedBy', fn () => $this->finalizedBy?->name),
            'finalized_at'         => $this->finalized_at?->toISOString(),
            'submitted_date'       => $this->submitted_date?->toDateString(),
            'submission_reference' => $this->submission_reference,
            'notes'                => $this->notes,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
