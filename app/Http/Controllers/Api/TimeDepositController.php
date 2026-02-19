<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Savings\AccrueTimeDepositInterestRequest;
use App\Http\Requests\Savings\PlaceTimeDepositRequest;
use App\Http\Requests\Savings\PreTerminateTimeDepositRequest;
use App\Http\Resources\TimeDepositResource;
use App\Http\Resources\TimeDepositTransactionResource;
use App\Models\TimeDeposit;
use App\Models\TimeDepositTransaction;
use App\Services\SavingsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeDepositController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SavingsService $savingsService,
    ) {
    }

    /**
     * GET /time-deposits
     * Paginated list with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TimeDeposit::with('customer:id,uuid,name,member_id')
            ->where('store_id', Auth::user()->store_id);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('maturing_days')) {
            $days = (int) $request->input('maturing_days', 30);
            $query->scopeMaturing($days);
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('account_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) =>
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('member_id', 'like', "%{$search}%")
                  )
            );
        }

        $deposits = $query->orderBy('placement_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $deposits->setCollection(
                $deposits->getCollection()->map(fn ($d) => new TimeDepositResource($d))
            ),
            'Time deposits retrieved successfully.'
        );
    }

    /**
     * POST /time-deposits
     * Place a new time deposit.
     */
    public function store(PlaceTimeDepositRequest $request): JsonResponse
    {
        try {
            $data             = $request->validated();
            $data['store_id'] = Auth::user()->store_id;

            $td = $this->savingsService->placeTimeDeposit($data, Auth::user());

            return $this->successResponse(
                new TimeDepositResource($td),
                'Time deposit placed successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to place time deposit.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /time-deposits/overview
     */
    public function overview(): JsonResponse
    {
        $overview = $this->savingsService->getTimeDepositPortfolioOverview(Auth::user()->store_id);
        return $this->successResponse($overview, 'Time deposit portfolio overview retrieved successfully.');
    }

    /**
     * POST /time-deposits/interest-preview
     * Compute expected interest without saving.
     */
    public function interestPreview(Request $request): JsonResponse
    {
        $request->validate([
            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'interest_rate'    => ['required', 'numeric', 'min:0.000001', 'max:1'],
            'term_months'      => ['required', 'integer', 'min:1', 'max:360'],
            'placement_date'   => ['nullable', 'date'],
        ]);

        $preview = $this->savingsService->computeInterestPreview($request->all());

        return $this->successResponse($preview, 'Interest preview computed successfully.');
    }

    /**
     * GET /time-deposits/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with([
                'customer:id,uuid,name,member_id,phone,mobile',
                'transactions.user:id,name',
            ])
            ->firstOrFail();

        return $this->successResponse(
            new TimeDepositResource($td),
            'Time deposit retrieved successfully.'
        );
    }

    /**
     * POST /time-deposits/{uuid}/accrue
     * Record periodic interest accrual.
     */
    public function accrueInterest(AccrueTimeDepositInterestRequest $request, string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $tx = $this->savingsService->accrueTimeDepositInterest($td, $request->validated(), Auth::user());
            $tx->load('user:id,name');

            return $this->successResponse(
                new TimeDepositTransactionResource($tx),
                'Interest accrued successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to accrue interest.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /time-deposits/{uuid}/mature
     * Process maturity payout.
     */
    public function mature(Request $request, string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'transaction_date' => ['nullable', 'date'],
            'payment_method'   => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $tx = $this->savingsService->matureTimeDeposit($td, $request->all(), Auth::user());
            $tx->load('user:id,name');

            return $this->successResponse(
                new TimeDepositTransactionResource($tx),
                'Time deposit matured and payout recorded.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process maturity.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /time-deposits/{uuid}/pre-terminate
     * Early termination with penalty.
     */
    public function preTerminate(PreTerminateTimeDepositRequest $request, string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $tx = $this->savingsService->preTerminate($td, $request->validated(), Auth::user());
            $tx->load('user:id,name');

            return $this->successResponse(
                new TimeDepositTransactionResource($tx),
                'Time deposit pre-terminated successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to pre-terminate time deposit.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /time-deposits/{uuid}/rollover
     * Roll over to a new time deposit.
     */
    public function rollOver(Request $request, string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'term_months'                    => ['nullable', 'integer', 'min:1', 'max:360'],
            'interest_rate'                  => ['nullable', 'numeric', 'min:0.000001', 'max:1'],
            'payment_frequency'              => ['nullable', 'string'],
            'interest_method'                => ['nullable', 'string'],
            'early_withdrawal_penalty_rate'  => ['nullable', 'numeric', 'min:0', 'max:1'],
            'placement_date'                 => ['nullable', 'date'],
            'include_interest'               => ['nullable', 'boolean'],
            'notes'                          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $newTd = $this->savingsService->rollOver($td, $request->all(), Auth::user());

            return $this->successResponse(
                new TimeDepositResource($newTd->load('customer:id,uuid,name,member_id')),
                'Time deposit rolled over successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to roll over time deposit.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /time-deposits/{uuid}/transactions
     */
    public function listTransactions(Request $request, string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $transactions = TimeDepositTransaction::where('time_deposit_id', $td->id)
            ->with('user:id,name')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $transactions->setCollection(
                $transactions->getCollection()->map(fn ($t) => new TimeDepositTransactionResource($t))
            ),
            'Time deposit transactions retrieved successfully.'
        );
    }

    /**
     * GET /time-deposits/{uuid}/statement
     */
    public function statement(string $uuid): JsonResponse
    {
        $td = TimeDeposit::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $statement = $this->savingsService->getTimeDepositStatement($td);

        return $this->successResponse($statement, 'Time deposit statement retrieved successfully.');
    }
}
