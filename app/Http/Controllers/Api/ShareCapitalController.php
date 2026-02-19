<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShareCapital\ComputeISCRequest;
use App\Http\Requests\ShareCapital\IssueShareCertificateRequest;
use App\Http\Requests\ShareCapital\OpenShareAccountRequest;
use App\Http\Requests\ShareCapital\RecordSharePaymentRequest;
use App\Http\Requests\ShareCapital\WithdrawSharesRequest;
use App\Http\Resources\MemberShareAccountResource;
use App\Http\Resources\ShareCapitalPaymentResource;
use App\Http\Resources\ShareCertificateResource;
use App\Models\MemberShareAccount;
use App\Models\ShareCapitalPayment;
use App\Models\ShareCertificate;
use App\Services\ShareCapitalService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShareCapitalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ShareCapitalService $shareCapitalService,
    ) {
    }

    /**
     * GET /share-capital
     * Paginated list of share accounts with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemberShareAccount::with('customer:id,uuid,name,member_id')
            ->where('store_id', Auth::user()->store_id);

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('member_id', 'like', "%{$search}%"))
                ->orWhere('account_number', 'like', "%{$search}%");
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('share_type')) {
            $query->where('share_type', $request->input('share_type'));
        }

        $accounts = $query->latest()->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $accounts->setCollection(
                $accounts->getCollection()->map(fn ($a) => new MemberShareAccountResource($a))
            ),
            'Share accounts retrieved successfully.'
        );
    }

    /**
     * POST /share-capital
     * Open a new share capital account for a member.
     */
    public function store(OpenShareAccountRequest $request): JsonResponse
    {
        try {
            $account = $this->shareCapitalService->openShareAccount($request->validated());
            $account->load('customer:id,uuid,name,member_id');

            return $this->successResponse(
                new MemberShareAccountResource($account),
                'Share account opened successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to open share account.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /share-capital/overview
     * Portfolio statistics.
     */
    public function overview(): JsonResponse
    {
        $overview = $this->shareCapitalService->getPortfolioOverview(Auth::user()->store_id);
        return $this->successResponse($overview, 'Share capital overview retrieved successfully.');
    }

    /**
     * POST /share-capital/compute-isc
     * Compute Interest on Share Capital for a fiscal year.
     */
    public function computeISC(ComputeISCRequest $request): JsonResponse
    {
        $result = $this->shareCapitalService->computeISC(
            storeId:      Auth::user()->store_id,
            year:         (int) $request->input('year'),
            dividendRate: (float) $request->input('dividend_rate'),
        );

        return $this->successResponse($result, 'ISC computation completed successfully.');
    }

    /**
     * GET /share-capital/{uuid}
     * Single share account with all related data.
     */
    public function show(string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->with(['customer:id,uuid,name,member_id', 'payments', 'certificates.issuedBy:id,name'])
            ->firstOrFail();

        return $this->successResponse(
            new MemberShareAccountResource($account),
            'Share account retrieved successfully.'
        );
    }

    /**
     * PUT /share-capital/{uuid}
     * Update notes or status.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $account->update($request->only(['notes', 'status']));

        return $this->successResponse(
            new MemberShareAccountResource($account->fresh()),
            'Share account updated successfully.'
        );
    }

    /**
     * POST /share-capital/{uuid}/payments
     * Record a payment installment.
     */
    public function recordPayment(RecordSharePaymentRequest $request, string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $payment = $this->shareCapitalService->recordPayment(
                $account,
                $request->validated(),
                Auth::user(),
            );
            $payment->load('user:id,name');

            return $this->successResponse(
                new ShareCapitalPaymentResource($payment),
                'Share payment recorded successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record payment.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /share-capital/{uuid}/payments
     * List payment history for the account.
     */
    public function listPayments(Request $request, string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $payments = ShareCapitalPayment::where('share_account_id', $account->id)
            ->with('user:id,name')
            ->orderBy('payment_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $payments->setCollection(
                $payments->getCollection()->map(fn ($p) => new ShareCapitalPaymentResource($p))
            ),
            'Payments retrieved successfully.'
        );
    }

    /**
     * DELETE /share-capital/{uuid}/payments/{payUuid}
     * Reverse a share payment.
     */
    public function reversePayment(string $uuid, string $payUuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $payment = ShareCapitalPayment::where('uuid', $payUuid)
            ->where('share_account_id', $account->id)
            ->firstOrFail();

        try {
            $reversed = $this->shareCapitalService->reversePayment($payment, Auth::user());

            return $this->successResponse(
                new ShareCapitalPaymentResource($reversed),
                'Payment reversed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reverse payment.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /share-capital/{uuid}/certificates
     * Issue a share certificate.
     */
    public function issueCertificate(IssueShareCertificateRequest $request, string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $certificate = $this->shareCapitalService->issueCertificate(
                $account,
                $request->validated(),
                Auth::user(),
            );
            $certificate->load('issuedBy:id,name');

            return $this->successResponse(
                new ShareCertificateResource($certificate),
                'Share certificate issued successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to issue certificate.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /share-capital/{uuid}/certificates
     * List certificates for the account.
     */
    public function listCertificates(string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $certificates = ShareCertificate::where('share_account_id', $account->id)
            ->with(['issuedBy:id,name', 'cancelledBy:id,name'])
            ->orderBy('issue_date', 'desc')
            ->get();

        return $this->successResponse(
            ShareCertificateResource::collection($certificates),
            'Certificates retrieved successfully.'
        );
    }

    /**
     * DELETE /share-capital/{uuid}/certificates/{certUuid}
     * Cancel a share certificate.
     */
    public function cancelCertificate(Request $request, string $uuid, string $certUuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $certificate = ShareCertificate::where('uuid', $certUuid)
            ->where('share_account_id', $account->id)
            ->firstOrFail();

        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        try {
            $cancelled = $this->shareCapitalService->cancelCertificate(
                $certificate,
                $request->input('reason'),
                Auth::user(),
            );

            return $this->successResponse(
                new ShareCertificateResource($cancelled),
                'Share certificate cancelled successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to cancel certificate.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /share-capital/{uuid}/statement
     * Generate an account statement for a date range.
     */
    public function statement(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $from = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::parse($account->opened_date);
        $to   = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : Carbon::today();

        $statement = $this->shareCapitalService->getStatement($account, $from, $to);

        return $this->successResponse($statement, 'Statement retrieved successfully.');
    }

    /**
     * POST /share-capital/{uuid}/withdraw
     * Process a member share withdrawal.
     */
    public function withdraw(WithdrawSharesRequest $request, string $uuid): JsonResponse
    {
        $account = MemberShareAccount::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $withdrawn = $this->shareCapitalService->withdrawShares(
                $account,
                $request->validated(),
                Auth::user(),
            );

            return $this->successResponse(
                new MemberShareAccountResource($withdrawn),
                'Share account withdrawn successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process withdrawal.', ['error' => $e->getMessage()], 500);
        }
    }
}
