<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maf\ApproveMafClaimRequest;
use App\Http\Requests\Maf\CreateMafProgramRequest;
use App\Http\Requests\Maf\FileMafClaimRequest;
use App\Http\Requests\Maf\PayMafClaimRequest;
use App\Http\Requests\Maf\RecordMafContributionRequest;
use App\Http\Requests\Maf\RegisterMafBeneficiaryRequest;
use App\Http\Requests\Maf\RejectMafClaimRequest;
use App\Http\Requests\Maf\ReverseMafContributionRequest;
use App\Http\Requests\Maf\UpdateMafBeneficiaryRequest;
use App\Http\Requests\Maf\UpdateMafProgramRequest;
use App\Models\Customer;
use App\Models\MafBeneficiary;
use App\Models\MafClaim;
use App\Models\MafContribution;
use App\Models\MafProgram;
use App\Services\MafService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MafController extends Controller
{
    public function __construct(protected MafService $mafService)
    {
    }

    // =========================================================================
    // A. Benefit Programs
    // =========================================================================

    public function programIndex(Request $request): JsonResponse
    {
        $query = MafProgram::where('store_id', auth()->user()->store_id);

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        if ($request->filled('benefit_type')) {
            $query->byBenefitType($request->benefit_type);
        }

        $programs = $query->orderBy('benefit_type')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $programs,
            'message' => 'MAF programs retrieved successfully.',
        ]);
    }

    public function programStore(CreateMafProgramRequest $request): JsonResponse
    {
        $program = $this->mafService->createProgram($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $program,
            'message' => "MAF program \"{$program->name}\" created successfully.",
        ], 201);
    }

    public function programShow(string $uuid): JsonResponse
    {
        $program = MafProgram::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->withCount('claims')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $program,
            'message' => 'MAF program retrieved successfully.',
        ]);
    }

    public function programUpdate(UpdateMafProgramRequest $request, string $uuid): JsonResponse
    {
        $program = MafProgram::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();

        $program = $this->mafService->updateProgram($program, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $program,
            'message' => 'MAF program updated successfully.',
        ]);
    }

    public function programDestroy(string $uuid): JsonResponse
    {
        $program = MafProgram::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();

        $hasClaims = $program->claims()->whereIn('status', ['pending', 'under_review', 'approved'])->exists();

        if ($hasClaims) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a program with open claims (pending, under review, or approved).',
            ], 422);
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'MAF program deleted successfully.',
        ]);
    }

    // =========================================================================
    // B. Beneficiaries (member-scoped)
    // =========================================================================

    public function beneficiaryIndex(string $customerUuid): JsonResponse
    {
        $customer = $this->resolveCustomer($customerUuid);

        $beneficiaries = MafBeneficiary::where('customer_id', $customer->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $beneficiaries,
            'message' => 'Beneficiaries retrieved successfully.',
        ]);
    }

    public function beneficiaryStore(RegisterMafBeneficiaryRequest $request, string $customerUuid): JsonResponse
    {
        $customer    = $this->resolveCustomer($customerUuid);
        $beneficiary = $this->mafService->registerBeneficiary($customer, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $beneficiary,
            'message' => "Beneficiary \"{$beneficiary->name}\" registered successfully.",
        ], 201);
    }

    public function beneficiaryUpdate(UpdateMafBeneficiaryRequest $request, string $customerUuid, string $bUuid): JsonResponse
    {
        $customer    = $this->resolveCustomer($customerUuid);
        $beneficiary = $this->resolveBeneficiary($bUuid, $customer->id);

        $beneficiary = $this->mafService->updateBeneficiary($beneficiary, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $beneficiary,
            'message' => 'Beneficiary updated successfully.',
        ]);
    }

    public function beneficiaryDeactivate(string $customerUuid, string $bUuid): JsonResponse
    {
        $customer    = $this->resolveCustomer($customerUuid);
        $beneficiary = $this->resolveBeneficiary($bUuid, $customer->id);

        $beneficiary = $this->mafService->deactivateBeneficiary($beneficiary);

        return response()->json([
            'success' => true,
            'data'    => $beneficiary,
            'message' => 'Beneficiary deactivated successfully.',
        ]);
    }

    // =========================================================================
    // C. Contributions
    // =========================================================================

    public function contributionIndex(Request $request): JsonResponse
    {
        $query = MafContribution::where('store_id', auth()->user()->store_id)
            ->with(['customer', 'user']);

        if ($request->filled('customer_uuid')) {
            $customer = Customer::where('uuid', $request->customer_uuid)
                ->where('store_id', auth()->user()->store_id)
                ->firstOrFail();
            $query->where('customer_id', $customer->id);
        }

        if ($request->filled('period_year')) {
            $query->where('period_year', $request->integer('period_year'));
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $contributions = $query->orderBy('contribution_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $contributions,
            'message' => 'MAF contributions retrieved successfully.',
        ]);
    }

    public function contributionStore(RecordMafContributionRequest $request): JsonResponse
    {
        $customerUuid = $request->input('customer_uuid');
        $customer     = Customer::where('uuid', $customerUuid)
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();

        $contribution = $this->mafService->recordContribution($customer, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $contribution,
            'message' => "Contribution {$contribution->contribution_number} recorded successfully.",
        ], 201);
    }

    public function contributionReverse(ReverseMafContributionRequest $request, string $uuid): JsonResponse
    {
        $contribution = MafContribution::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();

        $contribution = $this->mafService->reverseContribution(
            $contribution,
            $request->validated('reversal_reason'),
        );

        return response()->json([
            'success' => true,
            'data'    => $contribution,
            'message' => "Contribution {$contribution->contribution_number} reversed successfully.",
        ]);
    }

    // Member-scoped contribution history
    public function memberContributions(string $customerUuid): JsonResponse
    {
        $customer      = $this->resolveCustomer($customerUuid);
        $contributions = $this->mafService->getMemberContributions($customer);

        return response()->json([
            'success' => true,
            'data'    => $contributions,
            'message' => 'Member MAF contributions retrieved successfully.',
        ]);
    }

    // =========================================================================
    // D. Claims
    // =========================================================================

    public function claimIndex(Request $request): JsonResponse
    {
        $query = MafClaim::where('store_id', auth()->user()->store_id)
            ->with(['customer', 'mafProgram', 'beneficiary']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('benefit_type')) {
            $query->where('benefit_type', $request->benefit_type);
        }

        if ($request->filled('customer_uuid')) {
            $customer = Customer::where('uuid', $request->customer_uuid)
                ->where('store_id', auth()->user()->store_id)
                ->firstOrFail();
            $query->where('customer_id', $customer->id);
        }

        $claims = $query->orderBy('claim_date', 'desc')->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'data'       => $claims->items(),
                'pagination' => [
                    'current_page' => $claims->currentPage(),
                    'per_page'     => $claims->perPage(),
                    'total'        => $claims->total(),
                    'last_page'    => $claims->lastPage(),
                ],
            ],
            'message' => 'MAF claims retrieved successfully.',
        ]);
    }

    public function claimStore(FileMafClaimRequest $request): JsonResponse
    {
        $customerUuid = $request->input('customer_uuid');
        $customer     = Customer::where('uuid', $customerUuid)
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();

        $program = MafProgram::where('uuid', $request->input('maf_program_uuid'))
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();

        $claim = $this->mafService->fileClaim($customer, $program, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $claim,
            'message' => "MAF claim {$claim->claim_number} filed successfully.",
        ], 201);
    }

    public function claimShow(string $uuid): JsonResponse
    {
        $claim = MafClaim::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->with([
                'customer',
                'mafProgram',
                'beneficiary',
                'reviewedBy',
                'approvedBy',
                'rejectedBy',
                'paidBy',
                'payment',
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $claim,
            'message' => 'MAF claim retrieved successfully.',
        ]);
    }

    public function claimReview(string $uuid): JsonResponse
    {
        $claim = $this->resolveClaim($uuid);
        $claim = $this->mafService->reviewClaim($claim, auth()->user());

        return response()->json([
            'success' => true,
            'data'    => $claim,
            'message' => "Claim {$claim->claim_number} is now under review.",
        ]);
    }

    public function claimApprove(ApproveMafClaimRequest $request, string $uuid): JsonResponse
    {
        $claim            = $this->resolveClaim($uuid);
        $approvedCentavos = (int) round((float) $request->validated('approved_amount') * 100);

        $claim = $this->mafService->approveClaim($claim, $approvedCentavos, auth()->user());

        return response()->json([
            'success' => true,
            'data'    => $claim,
            'message' => "Claim {$claim->claim_number} approved for ₱" . number_format($approvedCentavos / 100, 2) . '.',
        ]);
    }

    public function claimReject(RejectMafClaimRequest $request, string $uuid): JsonResponse
    {
        $claim = $this->resolveClaim($uuid);
        $claim = $this->mafService->rejectClaim(
            $claim,
            $request->validated('rejection_reason'),
            auth()->user(),
        );

        return response()->json([
            'success' => true,
            'data'    => $claim,
            'message' => "Claim {$claim->claim_number} has been rejected.",
        ]);
    }

    public function claimPay(PayMafClaimRequest $request, string $uuid): JsonResponse
    {
        $claim   = $this->resolveClaim($uuid);
        $payment = $this->mafService->payClaim($claim, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $payment,
            'message' => "Claim {$claim->claim_number} paid — disbursement {$payment->payment_number} recorded.",
        ], 201);
    }

    // Member-scoped claim history
    public function memberClaims(string $customerUuid): JsonResponse
    {
        $customer = $this->resolveCustomer($customerUuid);
        $claims   = $this->mafService->getMemberClaims($customer);

        return response()->json([
            'success' => true,
            'data'    => $claims,
            'message' => 'Member MAF claims retrieved successfully.',
        ]);
    }

    // =========================================================================
    // E. Reporting
    // =========================================================================

    public function fundOverview(): JsonResponse
    {
        $store    = auth()->user()->store;
        $overview = $this->mafService->getFundOverview($store);

        return response()->json([
            'success' => true,
            'data'    => $overview,
            'message' => 'MAF fund overview retrieved successfully.',
        ]);
    }

    public function claimsReport(Request $request): JsonResponse
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $report = $this->mafService->getClaimsReport(auth()->user()->store, $from, $to);

        return response()->json([
            'success' => true,
            'data'    => $report,
            'message' => 'MAF claims report generated successfully.',
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function resolveCustomer(string $uuid): Customer
    {
        return Customer::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->firstOrFail();
    }

    private function resolveBeneficiary(string $uuid, int $customerId): MafBeneficiary
    {
        return MafBeneficiary::where('uuid', $uuid)
            ->where('customer_id', $customerId)
            ->firstOrFail();
    }

    private function resolveClaim(string $uuid): MafClaim
    {
        return MafClaim::where('uuid', $uuid)
            ->where('store_id', auth()->user()->store_id)
            ->with(['mafProgram', 'customer'])
            ->firstOrFail();
    }
}
