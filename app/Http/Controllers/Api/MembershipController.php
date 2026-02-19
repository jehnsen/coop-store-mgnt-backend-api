<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\ApproveMembershipApplicationRequest;
use App\Http\Requests\Membership\RecordMembershipFeeRequest;
use App\Http\Requests\Membership\RejectMembershipApplicationRequest;
use App\Http\Requests\Membership\SubmitMembershipApplicationRequest;
use App\Http\Resources\MembershipApplicationResource;
use App\Http\Resources\MembershipFeeResource;
use App\Models\Customer;
use App\Models\MembershipApplication;
use App\Models\MembershipFee;
use App\Services\MembershipService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MembershipService $membershipService,
    ) {
    }

    // =========================================================================
    // Overview
    // =========================================================================

    /**
     * GET /memberships/overview
     */
    public function overview(): JsonResponse
    {
        $overview = $this->membershipService->getOverview(Auth::user()->store_id);
        return $this->successResponse($overview, 'Membership overview retrieved successfully.');
    }

    // =========================================================================
    // Applications
    // =========================================================================

    /**
     * GET /memberships/applications
     * Paginated list of applications.
     */
    public function indexApplications(Request $request): JsonResponse
    {
        $query = MembershipApplication::with('customer:id,uuid,name,member_id,phone,mobile')
            ->where('store_id', Auth::user()->store_id)
            ->orderBy('application_date', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('application_type')) {
            $query->where('application_type', $request->input('application_type'));
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('application_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) =>
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('member_id', 'like', "%{$search}%")
                  )
            );
        }

        $applications = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $applications->setCollection(
                $applications->getCollection()->map(fn ($a) => new MembershipApplicationResource($a))
            ),
            'Membership applications retrieved successfully.'
        );
    }

    /**
     * POST /memberships/applications
     * Submit a new membership application.
     */
    public function submitApplication(SubmitMembershipApplicationRequest $request): JsonResponse
    {
        try {
            $application = $this->membershipService->submitApplication(
                $request->validated(),
                Auth::user()
            );

            $application->load('customer:id,uuid,name,member_id,phone,mobile');

            return $this->successResponse(
                new MembershipApplicationResource($application),
                'Membership application submitted successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to submit application.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /memberships/applications/{uuid}
     */
    public function showApplication(string $uuid): JsonResponse
    {
        $application = MembershipApplication::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with([
                'customer:id,uuid,name,member_id,phone,mobile,email',
                'reviewedBy:id,name',
                'fees.user:id,name',
            ])
            ->firstOrFail();

        return $this->successResponse(
            new MembershipApplicationResource($application),
            'Membership application retrieved successfully.'
        );
    }

    /**
     * POST /memberships/applications/{uuid}/approve
     */
    public function approveApplication(ApproveMembershipApplicationRequest $request, string $uuid): JsonResponse
    {
        $application = MembershipApplication::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $application = $this->membershipService->approveApplication(
                $application,
                $request->validated(),
                Auth::user()
            );

            $application->load([
                'customer:id,uuid,name,member_id,phone,mobile',
                'reviewedBy:id,name',
                'fees.user:id,name',
            ]);

            return $this->successResponse(
                new MembershipApplicationResource($application),
                'Membership application approved. Member is now active.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve application.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /memberships/applications/{uuid}/reject
     */
    public function rejectApplication(RejectMembershipApplicationRequest $request, string $uuid): JsonResponse
    {
        $application = MembershipApplication::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $application = $this->membershipService->rejectApplication(
                $application,
                $request->validated(),
                Auth::user()
            );

            $application->load(['customer:id,uuid,name,member_id', 'reviewedBy:id,name']);

            return $this->successResponse(
                new MembershipApplicationResource($application),
                'Membership application rejected.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject application.', ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Members (status transitions)
    // =========================================================================

    /**
     * GET /memberships/members
     * Paginated list of members (is_member=true).
     */
    public function indexMembers(Request $request): JsonResponse
    {
        $query = Customer::where('store_id', Auth::user()->store_id)
            ->where('is_member', true)
            ->orderBy('name');

        if ($request->has('member_status')) {
            $query->where('member_status', $request->input('member_status'));
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            );
        }

        $members = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $members->setCollection(
                $members->getCollection()->map(fn ($m) => [
                    'uuid'                   => $m->uuid,
                    'member_id'              => $m->member_id,
                    'name'                   => $m->name,
                    'email'                  => $m->email,
                    'phone'                  => $m->phone,
                    'mobile'                 => $m->mobile,
                    'address'                => $m->address,
                    'member_status'          => $m->member_status,
                    'is_active'              => $m->is_active,
                    'accumulated_patronage'  => number_format($m->getRawOriginal('accumulated_patronage') / 100, 2, '.', ''),
                    'created_at'             => $m->created_at?->toISOString(),
                ])
            ),
            'Members retrieved successfully.'
        );
    }

    /**
     * POST /memberships/members/{uuid}/deactivate
     */
    public function deactivate(Request $request, string $uuid): JsonResponse
    {
        $customer = Customer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $customer = $this->membershipService->deactivateMember($customer, $request->all(), Auth::user());
            return $this->successResponse(['member_status' => $customer->member_status], 'Member set to inactive.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /memberships/members/{uuid}/reinstate
     */
    public function reinstate(Request $request, string $uuid): JsonResponse
    {
        $customer = Customer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'reinstatement_fee_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method'           => ['nullable', 'in:cash,check,bank_transfer,gcash,maya,internal_transfer'],
            'reference_number'         => ['nullable', 'string', 'max:100'],
            'transaction_date'         => ['nullable', 'date', 'before_or_equal:today'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $customer = $this->membershipService->reinstateMember($customer, $request->all(), Auth::user());
            return $this->successResponse(['member_status' => $customer->member_status], 'Member reinstated to regular status.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /memberships/members/{uuid}/expel
     */
    public function expel(Request $request, string $uuid): JsonResponse
    {
        $customer = Customer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $customer = $this->membershipService->expelMember($customer, $request->all(), Auth::user());
            return $this->successResponse(['member_status' => $customer->member_status], 'Member expelled.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /memberships/members/{uuid}/resign
     */
    public function resign(Request $request, string $uuid): JsonResponse
    {
        $customer = Customer::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $customer = $this->membershipService->resignMember($customer, $request->all(), Auth::user());
            return $this->successResponse(['member_status' => $customer->member_status], 'Member resignation recorded.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }

    // =========================================================================
    // Membership Fees
    // =========================================================================

    /**
     * GET /memberships/fees
     * Paginated list of fee records.
     */
    public function indexFees(Request $request): JsonResponse
    {
        $query = MembershipFee::with(['customer:id,uuid,name,member_id', 'user:id,name'])
            ->where('store_id', Auth::user()->store_id)
            ->orderBy('transaction_date', 'desc');

        if ($request->has('fee_type')) {
            $query->where('fee_type', $request->input('fee_type'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_reversed', false);
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('fee_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) =>
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('member_id', 'like', "%{$search}%")
                  )
            );
        }

        $fees = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $fees->setCollection(
                $fees->getCollection()->map(fn ($f) => new MembershipFeeResource($f))
            ),
            'Membership fees retrieved successfully.'
        );
    }

    /**
     * POST /memberships/fees
     * Record a standalone membership fee payment.
     */
    public function recordFee(RecordMembershipFeeRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Resolve customer_uuid → customer_id
        $customer = Customer::where('uuid', $data['customer_uuid'])
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();
        $data['customer_id'] = $customer->id;

        try {
            $fee = $this->membershipService->recordFee($data, Auth::user());
            $fee->load(['customer:id,uuid,name,member_id', 'user:id,name']);

            return $this->successResponse(
                new MembershipFeeResource($fee),
                'Membership fee recorded successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record fee.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /memberships/fees/{uuid}
     * Reverse a membership fee entry.
     */
    public function reverseFee(string $uuid): JsonResponse
    {
        $fee = MembershipFee::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $fee = $this->membershipService->reverseFee($fee, Auth::user());
            $fee->load(['customer:id,uuid,name,member_id', 'user:id,name', 'reversedBy:id,name']);

            return $this->successResponse(
                new MembershipFeeResource($fee),
                'Membership fee reversed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reverse fee.', ['error' => $e->getMessage()], 500);
        }
    }
}
