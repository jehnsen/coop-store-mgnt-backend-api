<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\ApproveLoanRequest;
use App\Http\Requests\Loan\ComputeAmortizationRequest;
use App\Http\Requests\Loan\DisburseLoanRequest;
use App\Http\Requests\Loan\RecordLoanPaymentRequest;
use App\Http\Requests\Loan\RejectLoanRequest;
use App\Http\Requests\Loan\StoreLoanApplicationRequest;
use App\Http\Requests\Loan\WaivePenaltyRequest;
use App\Http\Resources\LoanAmortizationScheduleResource;
use App\Http\Resources\LoanPaymentResource;
use App\Http\Resources\LoanPenaltyResource;
use App\Http\Resources\LoanResource;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanAmortizationSchedule;
use App\Models\LoanPayment;
use App\Models\LoanPenalty;
use App\Services\AmortizationService;
use App\Services\LoanService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LoanService          $loanService,
        protected AmortizationService  $amortizationService,
    ) {
    }

    /**
     * GET /loans
     * Paginated loan list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Loan::where('store_id', Auth::user()->store_id)
            ->with(['customer:id,uuid,name,member_id', 'loanProduct:id,uuid,name,loan_type', 'officer:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('loan_type')) {
            $query->whereHas('loanProduct', fn ($q) => $q->where('loan_type', $request->input('loan_type')));
        }

        if ($request->has('customer_uuid')) {
            $customer = Customer::where('uuid', $request->input('customer_uuid'))
                ->where('store_id', Auth::user()->store_id)
                ->first();
            if ($customer) {
                $query->where('customer_id', $customer->id);
            }
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('loan_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
            );
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('application_date', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        $loans = $query->latest('application_date')->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $loans->setCollection(
                $loans->getCollection()->map(fn ($l) => new LoanResource($l))
            ),
            'Loans retrieved successfully.'
        );
    }

    /**
     * POST /loans
     * Submit a new loan application.
     */
    public function store(StoreLoanApplicationRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['principal_amount'] = (int) round($data['principal_amount'] * 100); // pesos → centavos

            $loan = $this->loanService->applyLoan($data, Auth::user());

            return $this->successResponse(
                new LoanResource($loan),
                'Loan application submitted successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to submit loan application.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /loans/overview
     */
    public function overview(): JsonResponse
    {
        $overview = $this->loanService->getPortfolioOverview(Auth::user()->store_id);
        return $this->successResponse($overview, 'Loan portfolio overview retrieved successfully.');
    }

    /**
     * GET /loans/delinquent
     */
    public function delinquent(): JsonResponse
    {
        $loans = $this->loanService->getDelinquentLoans(Auth::user()->store_id);
        return $this->successResponse(
            LoanResource::collection($loans),
            'Delinquent loans retrieved successfully.'
        );
    }

    /**
     * GET /loans/aging
     */
    public function aging(): JsonResponse
    {
        $aging = $this->loanService->getAgingReport(Auth::user()->store_id);
        return $this->successResponse($aging, 'Loan aging report retrieved successfully.');
    }

    /**
     * POST /loans/amortization/preview
     * Compute a schedule without saving.
     */
    public function previewAmortization(ComputeAmortizationRequest $request): JsonResponse
    {
        $principalCentavos = (int) round($request->input('principal_amount') * 100);
        $computed = $this->amortizationService->computeDiminishingBalance(
            principalCentavos: $principalCentavos,
            monthlyRate:        (float) $request->input('interest_rate'),
            termMonths:         (int) $request->input('term_months'),
            firstPaymentDate:   Carbon::parse($request->input('first_payment_date')),
            interval:           $request->input('payment_interval', 'monthly'),
        );

        // Convert centavos → pesos for display
        $computed['schedule'] = array_map(fn ($row) => array_merge($row, [
            'beginning_balance' => number_format($row['beginning_balance'] / 100, 2, '.', ''),
            'principal_due'     => number_format($row['principal_due'] / 100, 2, '.', ''),
            'interest_due'      => number_format($row['interest_due'] / 100, 2, '.', ''),
            'total_due'         => number_format($row['total_due'] / 100, 2, '.', ''),
            'ending_balance'    => number_format($row['ending_balance'] / 100, 2, '.', ''),
        ]), $computed['schedule']);

        $computed['total_interest'] = number_format($computed['total_interest'] / 100, 2, '.', '');
        $computed['total_payable']  = number_format($computed['total_payable'] / 100, 2, '.', '');
        $computed['emi_amount']     = number_format($computed['emi_centavos'] / 100, 2, '.', '');
        unset($computed['emi_centavos']);

        return $this->successResponse($computed, 'Amortization schedule computed successfully.');
    }

    /**
     * GET /loans/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with([
                'customer:id,uuid,name,member_id,phone,mobile',
                'loanProduct:id,uuid,name,loan_type,interest_rate',
                'officer:id,name',
                'approvedBy:id,name',
                'disbursedBy:id,name',
                'amortizationSchedules',
                'payments.user:id,name',
                'penalties.waivedBy:id,name',
            ])
            ->firstOrFail();

        return $this->successResponse(
            new LoanResource($loan),
            'Loan retrieved successfully.'
        );
    }

    /**
     * PUT /loans/{uuid}
     * Edit while pending.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        if (! in_array($loan->status, ['pending', 'under_review'])) {
            return $this->errorResponse('Only pending or under-review loans can be edited.', [], 422);
        }

        $data = $request->only(['purpose', 'collateral_description']);
        $loan->update($data);

        return $this->successResponse(
            new LoanResource($loan->fresh(['customer', 'loanProduct'])),
            'Loan updated successfully.'
        );
    }

    /**
     * POST /loans/{uuid}/approve
     */
    public function approve(ApproveLoanRequest $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $approved = $this->loanService->approveLoan($loan, Auth::user(), $request->validated());

            return $this->successResponse(
                new LoanResource($approved->load(['customer', 'loanProduct', 'approvedBy:id,name'])),
                'Loan approved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve loan.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /loans/{uuid}/reject
     */
    public function reject(RejectLoanRequest $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $rejected = $this->loanService->rejectLoan($loan, Auth::user(), $request->input('rejection_reason'));

            return $this->successResponse(
                new LoanResource($rejected->load('customer')),
                'Loan rejected.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject loan.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /loans/{uuid}/disburse
     */
    public function disburse(DisburseLoanRequest $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $disbursed = $this->loanService->disburseLoan($loan, Auth::user(), $request->validated());

            return $this->successResponse(
                new LoanResource($disbursed->load(['customer', 'loanProduct', 'amortizationSchedules', 'disbursedBy:id,name'])),
                'Loan disbursed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to disburse loan.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /loans/{uuid}/payments
     */
    public function recordPayment(RecordLoanPaymentRequest $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $data = $request->validated();
            $data['amount'] = (int) round($data['amount'] * 100); // pesos → centavos

            $payment = $this->loanService->recordPayment($loan, $data, Auth::user());
            $payment->load('user:id,name');

            return $this->successResponse(
                new LoanPaymentResource($payment),
                'Loan payment recorded successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record loan payment.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /loans/{uuid}/payments
     */
    public function listPayments(Request $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $payments = LoanPayment::where('loan_id', $loan->id)
            ->with('user:id,name')
            ->orderBy('payment_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $payments->setCollection(
                $payments->getCollection()->map(fn ($p) => new LoanPaymentResource($p))
            ),
            'Loan payments retrieved successfully.'
        );
    }

    /**
     * DELETE /loans/{uuid}/payments/{payUuid}
     */
    public function reversePayment(string $uuid, string $payUuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $payment = LoanPayment::where('uuid', $payUuid)
            ->where('loan_id', $loan->id)
            ->firstOrFail();

        try {
            $reversed = $this->loanService->reversePayment($payment, Auth::user());

            return $this->successResponse(
                new LoanPaymentResource($reversed),
                'Loan payment reversed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reverse loan payment.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /loans/{uuid}/schedule
     */
    public function schedule(string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $schedules = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->get();

        return $this->successResponse(
            LoanAmortizationScheduleResource::collection($schedules),
            'Amortization schedule retrieved successfully.'
        );
    }

    /**
     * GET /loans/{uuid}/statement
     */
    public function statement(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $from = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::parse($loan->application_date);
        $to   = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : Carbon::today();

        $statement = $this->loanService->getLoanStatement($loan, $from, $to);

        return $this->successResponse($statement, 'Loan statement retrieved successfully.');
    }

    /**
     * POST /loans/{uuid}/penalties/compute
     */
    public function computePenalties(Request $request, string $uuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'as_of_date'   => ['nullable', 'date', 'before_or_equal:today'],
            'penalty_rate' => ['nullable', 'numeric', 'min:0.0001', 'max:1'],
        ]);

        try {
            $asOfDate    = $request->input('as_of_date') ? Carbon::parse($request->input('as_of_date')) : null;
            $penaltyRate = $request->input('penalty_rate', 0.02);

            $penalties = $this->loanService->computePenalties($loan, $asOfDate, (float) $penaltyRate);

            return $this->successResponse(
                LoanPenaltyResource::collection($penalties),
                count($penalties) > 0
                    ? count($penalties) . ' penalty/penalties applied successfully.'
                    : 'No overdue schedules found.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to compute penalties.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /loans/{uuid}/penalties/{penUuid}/waive
     */
    public function waivePenalty(WaivePenaltyRequest $request, string $uuid, string $penUuid): JsonResponse
    {
        $loan = Loan::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $penalty = LoanPenalty::where('uuid', $penUuid)
            ->where('loan_id', $loan->id)
            ->firstOrFail();

        try {
            $waivedCentavos = (int) round($request->input('waived_amount') * 100);
            $waived = $this->loanService->waivePenalty(
                $penalty,
                $waivedCentavos,
                $request->input('reason'),
                Auth::user(),
            );

            return $this->successResponse(
                new LoanPenaltyResource($waived->load('waivedBy:id,name')),
                'Penalty waived successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to waive penalty.', ['error' => $e->getMessage()], 500);
        }
    }
}
