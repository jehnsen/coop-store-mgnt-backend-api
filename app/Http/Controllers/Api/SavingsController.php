<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Savings\CloseSavingsAccountRequest;
use App\Http\Requests\Savings\CreditSavingsInterestRequest;
use App\Http\Requests\Savings\OpenSavingsAccountRequest;
use App\Http\Requests\Savings\RecordSavingsDepositRequest;
use App\Http\Requests\Savings\RecordSavingsWithdrawalRequest;
use App\Http\Resources\MemberSavingsAccountResource;
use App\Http\Resources\SavingsTransactionResource;
use App\Models\MemberSavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\SavingsService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SavingsService $savingsService,
    ) {
    }

    /**
     * GET /savings
     * Paginated list of savings accounts with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemberSavingsAccount::with('customer:id,uuid,name,member_id')
            ->where('store_id', Auth::user()->store_id);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('savings_type')) {
            $query->where('savings_type', $request->input('savings_type'));
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

        $accounts = $query->latest('opened_date')->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $accounts->setCollection(
                $accounts->getCollection()->map(fn ($a) => new MemberSavingsAccountResource($a))
            ),
            'Savings accounts retrieved successfully.'
        );
    }

    /**
     * POST /savings
     * Open a new savings account for a member.
     */
    public function store(OpenSavingsAccountRequest $request): JsonResponse
    {
        try {
            $data             = $request->validated();
            $data['store_id'] = Auth::user()->store_id;

            $account = $this->savingsService->openSavingsAccount($data);
            $account->load('customer:id,uuid,name,member_id');

            return $this->successResponse(
                new MemberSavingsAccountResource($account),
                'Savings account opened successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to open savings account.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /savings/overview
     */
    public function overview(): JsonResponse
    {
        $overview = $this->savingsService->getPortfolioOverview(Auth::user()->store_id);
        return $this->successResponse($overview, 'Savings portfolio overview retrieved successfully.');
    }

    /**
     * POST /savings/batch-credit-interest
     * Credit monthly savings interest to all active voluntary accounts.
     */
    public function batchCreditInterest(CreditSavingsInterestRequest $request): JsonResponse
    {
        try {
            $result = $this->savingsService->batchCreditInterest(
                Auth::user()->store_id,
                $request->validated(),
                Auth::user()
            );

            return $this->successResponse($result, 'Batch interest crediting completed.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to credit interest.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /savings/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with(['customer:id,uuid,name,member_id,phone,mobile', 'transactions.user:id,name'])
            ->firstOrFail();

        return $this->successResponse(
            new MemberSavingsAccountResource($account),
            'Savings account retrieved successfully.'
        );
    }

    /**
     * PUT /savings/{uuid}
     * Update notes, minimum_balance, or interest_rate.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $request->validate([
            'notes'           => ['nullable', 'string', 'max:1000'],
            'minimum_balance' => ['nullable', 'numeric', 'min:0'],
            'interest_rate'   => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $data = $request->only(['notes', 'interest_rate']);

        if ($request->has('minimum_balance')) {
            $data['minimum_balance'] = (int) round($request->input('minimum_balance') * 100);
        }

        $account->update($data);

        return $this->successResponse(
            new MemberSavingsAccountResource($account->fresh()),
            'Savings account updated successfully.'
        );
    }

    /**
     * POST /savings/{uuid}/deposit
     */
    public function deposit(RecordSavingsDepositRequest $request, string $uuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $transaction = $this->savingsService->deposit($account, $request->validated(), Auth::user());
            $transaction->load('user:id,name');

            return $this->successResponse(
                new SavingsTransactionResource($transaction),
                'Deposit recorded successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record deposit.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /savings/{uuid}/withdraw
     */
    public function withdraw(RecordSavingsWithdrawalRequest $request, string $uuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $transaction = $this->savingsService->withdraw($account, $request->validated(), Auth::user());
            $transaction->load('user:id,name');

            return $this->successResponse(
                new SavingsTransactionResource($transaction),
                'Withdrawal recorded successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record withdrawal.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /savings/{uuid}/transactions
     */
    public function listTransactions(Request $request, string $uuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $transactions = SavingsTransaction::where('savings_account_id', $account->id)
            ->with('user:id,name')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $transactions->setCollection(
                $transactions->getCollection()->map(fn ($t) => new SavingsTransactionResource($t))
            ),
            'Savings transactions retrieved successfully.'
        );
    }

    /**
     * DELETE /savings/{uuid}/transactions/{txUuid}
     * Reverse a savings transaction.
     */
    public function reverseTransaction(string $uuid, string $txUuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $transaction = SavingsTransaction::where('uuid', $txUuid)
            ->where('savings_account_id', $account->id)
            ->firstOrFail();

        try {
            $reversed = $this->savingsService->reverseSavingsTransaction($transaction, Auth::user());

            return $this->successResponse(
                new SavingsTransactionResource($reversed),
                'Transaction reversed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reverse transaction.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /savings/{uuid}/statement
     */
    public function statement(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $from = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::parse($account->opened_date);
        $to   = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : Carbon::today();

        $statement = $this->savingsService->getSavingsStatement($account, $from, $to);

        return $this->successResponse($statement, 'Statement retrieved successfully.');
    }

    /**
     * POST /savings/{uuid}/close
     */
    public function close(CloseSavingsAccountRequest $request, string $uuid): JsonResponse
    {
        $account = MemberSavingsAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $closed = $this->savingsService->closeSavingsAccount(
                $account,
                $request->validated(),
                Auth::user()
            );

            return $this->successResponse(
                new MemberSavingsAccountResource($closed->load('customer:id,uuid,name,member_id')),
                'Savings account closed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to close savings account.', ['error' => $e->getMessage()], 500);
        }
    }
}
