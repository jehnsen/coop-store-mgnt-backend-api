<?php

namespace App\Http\Controllers\Api;

use App\Exports\CustomersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AdjustCreditLimitRequest;
use App\Http\Requests\Customer\RecordPaymentRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CreditAgingResource;
use App\Http\Resources\CreditTransactionResource;
use App\Http\Resources\CustomerResource;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Criteria\FilterByColumn;
use App\Repositories\Criteria\SearchMultipleColumns;
use App\Services\CreditService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller
{
    use ApiResponse;

    protected CreditService $creditService;
    protected CustomerRepositoryInterface $customerRepo;
    protected CreditTransactionRepositoryInterface $creditTransactionRepo;

    public function __construct(
        CreditService $creditService,
        CustomerRepositoryInterface $customerRepo,
        CreditTransactionRepositoryInterface $creditTransactionRepo
    ) {
        $this->creditService = $creditService;
        $this->customerRepo = $customerRepo;
        $this->creditTransactionRepo = $creditTransactionRepo;
    }

    /**
     * Display a paginated listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        // Reset criteria for fresh query
        $this->customerRepo->resetCriteria();

        // Search by name, phone, email, code
        if ($request->has('q')) {
            $this->customerRepo->pushCriteria(
                new SearchMultipleColumns(
                    $request->input('q'),
                    ['name', 'phone', 'mobile', 'email', 'code']
                )
            );
        }

        // Filter by type
        if ($request->has('type')) {
            $this->customerRepo->pushCriteria(
                new FilterByColumn('type', $request->input('type'))
            );
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $this->customerRepo->pushCriteria(
                new FilterByColumn('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN))
            );
        }

        // Filter by has outstanding balance
        if ($request->boolean('has_outstanding_balance')) {
            $this->customerRepo->pushCriteria(
                new FilterByColumn('total_outstanding', 0, '>')
            );
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $this->customerRepo->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $customers = $this->customerRepo->paginate($perPage);

        return $this->paginatedResponse(
            $customers->setCollection(
                $customers->getCollection()->map(fn($customer) => new CustomerResource($customer))
            ),
            'Customers retrieved successfully'
        );
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Auto-generate customer code
            $data['code'] = 'CUST-' . strtoupper(uniqid());
            $data['store_id'] = auth()->user()->store_id;

            // Initialize totals
            $data['total_outstanding'] = 0;
            $data['total_purchases'] = 0;
            $data['allow_credit'] = isset($data['credit_limit']) && $data['credit_limit'] > 0;

            // Map business_name to company_name
            if (isset($data['business_name'])) {
                $data['company_name'] = $data['business_name'];
                unset($data['business_name']);
            }

            // Map alternate_phone to mobile
            if (isset($data['alternate_phone'])) {
                $data['mobile'] = $data['alternate_phone'];
                unset($data['alternate_phone']);
            }

            // Create customer using repository
            $customer = $this->customerRepo->create($data);

            DB::commit();

            // Log activity
            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->log('Customer created');

            return $this->successResponse(
                new CustomerResource($customer),
                'Customer created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(string $uuid): JsonResponse
    {
        $customer = $this->customerRepo
            ->resetCriteria()
            ->with(['sales' => function ($query) {
                $query->select('id', 'customer_id');
            }])
            ->findByUuidOrFail($uuid);

        // Manually load sales count
        $customer->loadCount('sales');

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer retrieved successfully'
        );
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, string $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            $customer = $this->customerRepo->findByUuidOrFail($uuid);
            $data = $request->validated();

            // Map business_name to company_name
            if (isset($data['business_name'])) {
                $data['company_name'] = $data['business_name'];
                unset($data['business_name']);
            }

            // Map alternate_phone to mobile
            if (isset($data['alternate_phone'])) {
                $data['mobile'] = $data['alternate_phone'];
                unset($data['alternate_phone']);
            }

            // Update allow_credit based on credit_limit
            if (isset($data['credit_limit'])) {
                $data['allow_credit'] = $data['credit_limit'] > 0;
            }

            $customer->update($data);

            DB::commit();

            // Log activity
            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->log('Customer updated');

            return $this->successResponse(
                new CustomerResource($customer->fresh()),
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $customer = $this->customerRepo->findByUuidOrFail($uuid);

            // Check if customer has outstanding balance
            if ($customer->getRawOriginal('total_outstanding') > 0) {
                return $this->errorResponse(
                    'Cannot delete customer with outstanding balance of ₱' . number_format($customer->total_outstanding, 2),
                    422
                );
            }

            // Soft delete
            $customer->delete();

            // Log activity
            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->log('Customer deleted');

            return $this->successResponse(null, 'Customer deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get customer's credit transactions.
     */
    public function transactions(Request $request, string $uuid): JsonResponse
    {
        $customer = $this->customerRepo->findByUuidOrFail($uuid);

        // Reset criteria for fresh query
        $this->creditTransactionRepo->resetCriteria();

        // Get base query filtered by customer
        $this->creditTransactionRepo->where('customer_id', $customer->id);

        // Load relationships
        $this->creditTransactionRepo->with(['sale', 'user']);

        // Filter by type
        if ($request->has('type')) {
            $this->creditTransactionRepo->pushCriteria(
                new FilterByColumn('type', $request->input('type'))
            );
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $this->creditTransactionRepo->where('transaction_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ], 'BETWEEN');
        }

        // Sorting
        $this->creditTransactionRepo->orderBy('transaction_date', 'desc');

        // Pagination
        $perPage = $request->input('per_page', 15);
        $transactions = $this->creditTransactionRepo->paginate($perPage);

        return $this->paginatedResponse(
            $transactions->setCollection(
                $transactions->getCollection()->map(fn($transaction) => new CreditTransactionResource($transaction))
            ),
            'Credit transactions retrieved successfully'
        );
    }

    /**
     * Get customer's credit ledger (alias for transactions with detailed view).
     */
    public function creditLedger(Request $request, string $uuid): JsonResponse
    {
        return $this->transactions($request, $uuid);
    }

    /**
     * Record a payment from customer.
     */
    public function recordPayment(RecordPaymentRequest $request, string $uuid): JsonResponse
    {
        try {
            $customer = $this->customerRepo->findByUuidOrFail($uuid);

            $result = $this->creditService->receivePayment(
                $customer,
                $request->input('amount'),
                $request->input('payment_method'),
                $request->input('reference_number'),
                $request->input('invoice_ids'),
                $request->input('notes')
            );

            return $this->successResponse([
                'transaction' => new CreditTransactionResource($result['transaction']),
                'applied_to' => $result['applied_to'],
                'remaining_credit' => $result['remaining_credit'],
            ], 'Payment recorded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Adjust customer's credit limit.
     */
    public function adjustCreditLimit(AdjustCreditLimitRequest $request, string $uuid): JsonResponse
    {
        try {
            $customer = $this->customerRepo->findByUuidOrFail($uuid);

            $updatedCustomer = $this->creditService->adjustCreditLimit(
                $customer,
                $request->input('credit_limit'),
                $request->input('reason')
            );

            return $this->successResponse(
                new CustomerResource($updatedCustomer),
                'Credit limit adjusted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to adjust credit limit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get customer statement for a date range.
     */
    public function statement(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $customer = $this->customerRepo->findByUuidOrFail($uuid);

        $statement = $this->creditService->getCustomerStatement(
            $customer,
            Carbon::parse($request->input('start_date')),
            Carbon::parse($request->input('end_date'))
        );

        return $this->successResponse($statement, 'Statement retrieved successfully');
    }

    /**
     * Generate and download customer statement as PDF.
     */
    public function statementPdf(Request $request, string $uuid)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $customer = $this->customerRepo->findByUuidOrFail($uuid);

        $statement = $this->creditService->getCustomerStatement(
            $customer,
            Carbon::parse($request->input('start_date')),
            Carbon::parse($request->input('end_date'))
        );

        // Get store information
        $store = auth()->user()->store;

        $pdf = Pdf::loadView('statements.customer', [
            'statement' => $statement,
            'store' => $store,
        ]);

        return $pdf->download("statement-{$customer->code}-" . now()->format('Y-m-d') . ".pdf");
    }

    /**
     * Send reminder about outstanding balance.
     */
    public function sendReminder(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'channel' => 'required|in:sms,email,both',
            'message' => 'nullable|string|max:500',
        ]);

        $customer = $this->customerRepo->findByUuidOrFail($uuid);

        if ($customer->getRawOriginal('total_outstanding') <= 0) {
            return $this->errorResponse('Customer has no outstanding balance', 422);
        }

        // TODO: Implement SMS/Email sending
        // This is a placeholder for the actual implementation

        // Log activity
        activity()
            ->performedOn($customer)
            ->causedBy(auth()->user())
            ->withProperties([
                'channel' => $request->input('channel'),
                'outstanding_balance' => $customer->total_outstanding,
            ])
            ->log('Payment reminder sent to customer');

        return $this->successResponse(null, 'Reminder sent successfully');
    }

    /**
     * Get credit overview statistics.
     */
    public function creditOverview(): JsonResponse
    {
        $stats = $this->customerRepo->getCreditOverviewStats();

        // Convert from centavos to pesos for display
        $totalOutstanding = $stats['total_outstanding'] / 100;
        $totalCreditLimit = $stats['total_credit_limit'] / 100;
        $availableCredit = $stats['available_credit'] / 100;

        // Count customers with credit enabled
        $this->customerRepo->resetCriteria();
        $totalCustomersWithCredit = $this->customerRepo
            ->where('allow_credit', true)
            ->count();

        $response = [
            'total_customers_with_credit' => $totalCustomersWithCredit,
            'total_outstanding' => $totalOutstanding,
            'total_credit_limit' => $totalCreditLimit,
            'customers_with_balance' => $stats['customers_with_balance'],
            'total_available_credit' => $availableCredit,
            'average_credit_utilization' => $totalCreditLimit > 0
                ? round(($totalOutstanding / $totalCreditLimit) * 100, 2)
                : 0,
        ];

        return $this->successResponse($response, 'Credit overview retrieved successfully');
    }

    /**
     * Get credit aging report.
     */
    public function creditAging(): JsonResponse
    {
        $store = auth()->user()->store;

        $report = $this->creditService->getAgingReport($store);

        return $this->successResponse([
            'customers' => CreditAgingResource::collection($report['customers']),
            'summary' => $report['summary'],
            'customer_count' => $report['customer_count'],
        ], 'Credit aging report retrieved successfully');
    }

    /**
     * Get overdue accounts.
     */
    public function overdue(): JsonResponse
    {
        $store = auth()->user()->store;

        $overdueAccounts = $this->creditService->getOverdueAccounts($store);

        return $this->successResponse([
            'accounts' => $overdueAccounts,
            'total_overdue' => $overdueAccounts->sum('overdue_amount'),
            'account_count' => $overdueAccounts->count(),
        ], 'Overdue accounts retrieved successfully');
    }

    /**
     * Export customers to Excel.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'format' => 'nullable|in:xlsx,csv',
            'type' => 'nullable|in:walk_in,regular,contractor,government',
            'is_active' => 'nullable|boolean',
        ]);

        // Reset criteria for fresh query
        $this->customerRepo->resetCriteria();

        // Apply filters
        if ($request->has('type')) {
            $this->customerRepo->pushCriteria(
                new FilterByColumn('type', $request->input('type'))
            );
        }

        if ($request->has('is_active')) {
            $this->customerRepo->pushCriteria(
                new FilterByColumn('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN))
            );
        }

        // Get all customers (no pagination for export)
        $customers = $this->customerRepo->all();

        // Determine file format and extension
        $format = $request->input('format', 'xlsx');
        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        // Generate filename with timestamp
        $filename = 'customers_' . now()->format('Y-m-d_His') . '.' . $extension;

        // Export and download
        return Excel::download(new CustomersExport($customers), $filename, $writerType);
    }
}
