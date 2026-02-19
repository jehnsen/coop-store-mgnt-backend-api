<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cda\CompileAnnualReportRequest;
use App\Http\Requests\Cda\CreateAgaRecordRequest;
use App\Http\Requests\Cda\CreateCoopOfficerRequest;
use App\Http\Requests\Cda\MarkReportSubmittedRequest;
use App\Http\Requests\Cda\UpdateReportMetaRequest;
use App\Http\Resources\AgaRecordResource;
use App\Http\Resources\CdaAnnualReportResource;
use App\Http\Resources\CoopOfficerResource;
use App\Models\AgaRecord;
use App\Models\CdaAnnualReport;
use App\Models\CoopOfficer;
use App\Models\Customer;
use App\Services\CdaComplianceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CdaComplianceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CdaComplianceService $cdaService,
    ) {
    }

    // =========================================================================
    // Overview
    // =========================================================================

    /**
     * GET /cda/overview
     */
    public function overview(): JsonResponse
    {
        return $this->successResponse(
            $this->cdaService->getOverview(Auth::user()->store_id),
            'CDA compliance overview retrieved successfully.'
        );
    }

    // =========================================================================
    // Annual Reports
    // =========================================================================

    /**
     * GET /cda/reports
     */
    public function indexReports(Request $request): JsonResponse
    {
        $query = CdaAnnualReport::where('store_id', Auth::user()->store_id)
            ->orderBy('report_year', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $reports = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $reports->setCollection(
                $reports->getCollection()->map(fn ($r) => new CdaAnnualReportResource($r))
            ),
            'Annual reports retrieved successfully.'
        );
    }

    /**
     * POST /cda/reports/compile
     * Compile (or recompile) annual report data for a given year.
     */
    public function compile(CompileAnnualReportRequest $request): JsonResponse
    {
        try {
            $report = $this->cdaService->compileAnnualReport(
                Auth::user()->store_id,
                $request->input('report_year'),
                $request->validated(),
                Auth::user()
            );

            return $this->successResponse(
                new CdaAnnualReportResource($report->load(['compiledBy:id,name'])),
                "Annual report for {$report->report_year} compiled successfully.",
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to compile report.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /cda/reports/{uuid}
     */
    public function showReport(string $uuid): JsonResponse
    {
        $report = CdaAnnualReport::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with(['compiledBy:id,name', 'finalizedBy:id,name'])
            ->firstOrFail();

        return $this->successResponse(
            new CdaAnnualReportResource($report),
            'Annual report retrieved successfully.'
        );
    }

    /**
     * PUT /cda/reports/{uuid}
     * Update metadata (CDA reg number, cooperative type) without recompiling.
     */
    public function updateReport(UpdateReportMetaRequest $request, string $uuid): JsonResponse
    {
        $report = CdaAnnualReport::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $report = $this->cdaService->updateReportMeta($report, $request->validated());

            return $this->successResponse(
                new CdaAnnualReportResource($report),
                'Report metadata updated successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /cda/reports/{uuid}/finalize
     */
    public function finalizeReport(string $uuid): JsonResponse
    {
        $report = CdaAnnualReport::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $report = $this->cdaService->finalizeReport($report, Auth::user());

            return $this->successResponse(
                new CdaAnnualReportResource($report->load(['compiledBy:id,name', 'finalizedBy:id,name'])),
                'Annual report finalized.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /cda/reports/{uuid}/mark-submitted
     */
    public function markSubmitted(MarkReportSubmittedRequest $request, string $uuid): JsonResponse
    {
        $report = CdaAnnualReport::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $report = $this->cdaService->markSubmitted($report, $request->validated(), Auth::user());

            return $this->successResponse(
                new CdaAnnualReportResource($report),
                'Report marked as submitted to CDA.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * GET /cda/reports/{uuid}/statistical-data
     * Returns data structured for CDA Statistical Form 1 (General Information Sheet).
     */
    public function statisticalData(string $uuid): JsonResponse
    {
        $report = CdaAnnualReport::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $data = $this->cdaService->getStatisticalFormData($report);

        return $this->successResponse($data, 'Statistical form data retrieved successfully.');
    }

    // =========================================================================
    // AGA Records
    // =========================================================================

    /**
     * GET /cda/aga
     */
    public function indexAga(Request $request): JsonResponse
    {
        $query = AgaRecord::where('store_id', Auth::user()->store_id)
            ->orderBy('meeting_date', 'desc');

        if ($request->has('meeting_year')) {
            $query->where('meeting_year', $request->input('meeting_year'));
        }

        if ($request->has('meeting_type')) {
            $query->where('meeting_type', $request->input('meeting_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $records = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $records->setCollection(
                $records->getCollection()->map(fn ($r) => new AgaRecordResource($r))
            ),
            'AGA records retrieved successfully.'
        );
    }

    /**
     * POST /cda/aga
     */
    public function storeAga(CreateAgaRecordRequest $request): JsonResponse
    {
        try {
            $record = $this->cdaService->createAgaRecord($request->validated(), Auth::user());

            return $this->successResponse(
                new AgaRecordResource($record),
                'AGA record created successfully.',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create AGA record.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /cda/aga/{uuid}
     */
    public function showAga(string $uuid): JsonResponse
    {
        $record = AgaRecord::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with('finalizedBy:id,name')
            ->firstOrFail();

        return $this->successResponse(
            new AgaRecordResource($record),
            'AGA record retrieved successfully.'
        );
    }

    /**
     * PUT /cda/aga/{uuid}
     */
    public function updateAga(Request $request, string $uuid): JsonResponse
    {
        $record = AgaRecord::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'meeting_date'       => ['nullable', 'date'],
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
        ]);

        try {
            $record = $this->cdaService->updateAgaRecord($record, $request->all(), Auth::user());

            return $this->successResponse(
                new AgaRecordResource($record),
                'AGA record updated successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /cda/aga/{uuid}/finalize
     * Lock AGA minutes from further edits.
     */
    public function finalizeAga(string $uuid): JsonResponse
    {
        $record = AgaRecord::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $record = $this->cdaService->finalizeAgaRecord($record, Auth::user());

            return $this->successResponse(
                new AgaRecordResource($record->load('finalizedBy:id,name')),
                'AGA minutes finalized.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * DELETE /cda/aga/{uuid}
     * Soft-delete a draft AGA record.
     */
    public function destroyAga(string $uuid): JsonResponse
    {
        $record = AgaRecord::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        if ($record->status === 'finalized') {
            return $this->errorResponse('Finalized AGA records cannot be deleted.', [], 422);
        }

        $record->delete();

        return $this->successResponse(null, 'AGA record deleted.');
    }

    // =========================================================================
    // Officers
    // =========================================================================

    /**
     * GET /cda/officers
     */
    public function indexOfficers(Request $request): JsonResponse
    {
        $query = CoopOfficer::with('customer:id,uuid,name,member_id')
            ->where('store_id', Auth::user()->store_id)
            ->orderBy('committee')
            ->orderBy('position');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->where('is_active', true); // default: active only
        }

        if ($request->has('committee')) {
            $query->where('committee', $request->input('committee'));
        }

        $officers = $query->paginate($request->input('per_page', 50));

        return $this->paginatedResponse(
            $officers->setCollection(
                $officers->getCollection()->map(fn ($o) => new CoopOfficerResource($o))
            ),
            'Officers retrieved successfully.'
        );
    }

    /**
     * POST /cda/officers
     */
    public function storeOfficer(CreateCoopOfficerRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Resolve customer_uuid → customer_id if provided
        if (! empty($data['customer_uuid'])) {
            $customer = Customer::where('uuid', $data['customer_uuid'])
                ->where('store_id', Auth::user()->store_id)
                ->firstOrFail();
            $data['customer_id'] = $customer->id;
        }

        try {
            $officer = $this->cdaService->createOfficer($data, Auth::user());
            $officer->load('customer:id,uuid,name,member_id');

            return $this->successResponse(
                new CoopOfficerResource($officer),
                'Officer record created successfully.',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create officer.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /cda/officers/{uuid}
     */
    public function showOfficer(string $uuid): JsonResponse
    {
        $officer = CoopOfficer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with('customer:id,uuid,name,member_id')
            ->firstOrFail();

        return $this->successResponse(
            new CoopOfficerResource($officer),
            'Officer retrieved successfully.'
        );
    }

    /**
     * PUT /cda/officers/{uuid}
     */
    public function updateOfficer(Request $request, string $uuid): JsonResponse
    {
        $officer = CoopOfficer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'name'      => ['nullable', 'string', 'max:150'],
            'position'  => ['nullable', 'string', 'max:100'],
            'committee' => ['nullable', 'string', 'max:100'],
            'term_from' => ['nullable', 'date'],
            'term_to'   => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $officer = $this->cdaService->updateOfficer($officer, $request->all());
            $officer->load('customer:id,uuid,name,member_id');

            return $this->successResponse(
                new CoopOfficerResource($officer),
                'Officer updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update officer.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /cda/officers/{uuid}
     * Soft-delete (retire) an officer record.
     */
    public function destroyOfficer(string $uuid): JsonResponse
    {
        $officer = CoopOfficer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $officer->update(['is_active' => false, 'term_to' => $officer->term_to ?? today()]);
        $officer->delete();

        return $this->successResponse(null, 'Officer record retired and removed.');
    }
}
