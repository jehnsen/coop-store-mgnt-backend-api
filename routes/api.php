<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitOfMeasureController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\AccountsPayableController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShareCapitalController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\TimeDepositController;
use App\Http\Controllers\Api\PatronageRefundController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\CdaComplianceController;
use App\Http\Controllers\Api\MafController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected routes (require authentication)
Route::prefix('v1')->middleware(['auth:sanctum', 'store.access'])->group(function () {
    // Authentication routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Products API
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'barcode']);
    Route::get('/products/{uuid}', [ProductController::class, 'show']);
    Route::put('/products/{uuid}', [ProductController::class, 'update']);
    Route::delete('/products/{uuid}', [ProductController::class, 'destroy']);
    Route::post('/products/{uuid}/adjust-stock', [ProductController::class, 'adjustStock']);
    Route::get('/products/{uuid}/stock-history', [ProductController::class, 'stockHistory']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);

    // Categories API
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{uuid}', [CategoryController::class, 'show']);
    Route::put('/categories/{uuid}', [CategoryController::class, 'update']);
    Route::delete('/categories/{uuid}', [CategoryController::class, 'destroy']);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);

    // Units of Measure API
    Route::get('/units', [UnitOfMeasureController::class, 'index']);
    Route::post('/units', [UnitOfMeasureController::class, 'store']);
    Route::get('/units/{id}', [UnitOfMeasureController::class, 'show']);
    Route::put('/units/{id}', [UnitOfMeasureController::class, 'update']);
    Route::delete('/units/{id}', [UnitOfMeasureController::class, 'destroy']);

    // Sales (Point of Sale) API
    Route::prefix('sales')->group(function () {
        // Main sales operations
        Route::get('/', [SaleController::class, 'index']);
        Route::post('/', [SaleController::class, 'store']);
        Route::get('/{uuid}', [SaleController::class, 'show']);

        // Sale actions
        Route::post('/{uuid}/void', [SaleController::class, 'void']);
        Route::post('/{uuid}/refund', [SaleController::class, 'refund']);

        // Receipt operations
        Route::get('/{uuid}/receipt', [SaleController::class, 'getReceipt']);
        Route::get('/{uuid}/receipt/pdf', [SaleController::class, 'getReceiptPdf']);
        Route::post('/{uuid}/receipt/send', [SaleController::class, 'sendReceipt']);

        // Held transactions
        Route::post('/hold', [SaleController::class, 'holdTransaction']);
        Route::get('/held/list', [SaleController::class, 'listHeldTransactions']);
        Route::get('/held/{id}/resume', [SaleController::class, 'resumeHeldTransaction']);
        Route::delete('/held/{id}', [SaleController::class, 'discardHeldTransaction']);

        // Utilities
        Route::get('/next-number/preview', [SaleController::class, 'getNextSaleNumber']);
    });

    // Customers API
    Route::get('/customers/credit/overview', [CustomerController::class, 'creditOverview']);
    Route::get('/customers/credit/aging', [CustomerController::class, 'creditAging']);
    Route::get('/customers/credit/overdue', [CustomerController::class, 'overdue']);
    Route::get('/customers/export', [CustomerController::class, 'export']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{uuid}', [CustomerController::class, 'show']);
    Route::put('/customers/{uuid}', [CustomerController::class, 'update']);
    Route::delete('/customers/{uuid}', [CustomerController::class, 'destroy']);
    Route::get('/customers/{uuid}/transactions', [CustomerController::class, 'transactions']);
    Route::get('/customers/{uuid}/credit-ledger', [CustomerController::class, 'creditLedger']);
    Route::post('/customers/{uuid}/payments', [CustomerController::class, 'recordPayment']);
    Route::put('/customers/{uuid}/credit-limit', [CustomerController::class, 'adjustCreditLimit']);
    Route::get('/customers/{uuid}/statement', [CustomerController::class, 'statement']);
    Route::get('/customers/{uuid}/statement/pdf', [CustomerController::class, 'statementPdf']);
    Route::post('/customers/{uuid}/send-reminder', [CustomerController::class, 'sendReminder']);

    // Dashboard API
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/sales-trend', [DashboardController::class, 'salesTrend']);
        Route::get('/top-products', [DashboardController::class, 'topProducts']);
        Route::get('/sales-by-category', [DashboardController::class, 'salesByCategory']);
        Route::get('/credit-aging', [DashboardController::class, 'creditAging']);
        Route::get('/recent-transactions', [DashboardController::class, 'recentTransactions']);
        Route::get('/stock-alerts', [DashboardController::class, 'stockAlerts']);
        Route::get('/upcoming-deliveries', [DashboardController::class, 'upcomingDeliveries']);
        Route::get('/top-customers', [DashboardController::class, 'topCustomers']);
        Route::get('/comprehensive', [DashboardController::class, 'comprehensive']);
    });

    // Suppliers API
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{uuid}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{uuid}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{uuid}', [SupplierController::class, 'destroy']);
    Route::get('/suppliers/{uuid}/products', [SupplierController::class, 'products']);
    Route::post('/suppliers/{uuid}/products', [SupplierController::class, 'addProduct']);
    Route::delete('/suppliers/{uuid}/products/{productUuid}', [SupplierController::class, 'removeProduct']);
    Route::get('/suppliers/{uuid}/price-history', [SupplierController::class, 'priceHistory']);

    // Accounts Payable (AP) API
    Route::prefix('ap')->group(function () {
        Route::get('/overview', [AccountsPayableController::class, 'overview']);
        Route::get('/aging', [AccountsPayableController::class, 'aging']);
        Route::get('/overdue', [AccountsPayableController::class, 'overdue']);
        Route::get('/payment-schedule', [AccountsPayableController::class, 'paymentSchedule']);
        Route::get('/disbursement-report', [AccountsPayableController::class, 'disbursementReport']);
    });

    // Supplier-specific AP routes
    Route::get('/suppliers/{uuid}/payables', [AccountsPayableController::class, 'supplierPayables']);
    Route::get('/suppliers/{uuid}/ledger', [AccountsPayableController::class, 'supplierLedger']);
    Route::post('/suppliers/{uuid}/payments', [AccountsPayableController::class, 'makePayment']);
    Route::get('/suppliers/{uuid}/statement', [AccountsPayableController::class, 'statement']);

    // Purchase Orders API
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{uuid}', [PurchaseOrderController::class, 'show']);
    Route::put('/purchase-orders/{uuid}', [PurchaseOrderController::class, 'update']);
    Route::delete('/purchase-orders/{uuid}', [PurchaseOrderController::class, 'destroy']);
    Route::post('/purchase-orders/{uuid}/submit', [PurchaseOrderController::class, 'submit']);
    Route::post('/purchase-orders/{uuid}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('/purchase-orders/{uuid}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::get('/purchase-orders/{uuid}/pdf', [PurchaseOrderController::class, 'pdf']);

    // Deliveries API
    Route::get('/deliveries/today-schedule', [DeliveryController::class, 'todaySchedule']);
    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::post('/deliveries', [DeliveryController::class, 'store']);
    Route::get('/deliveries/{uuid}', [DeliveryController::class, 'show']);
    Route::put('/deliveries/{uuid}', [DeliveryController::class, 'update']);
    Route::delete('/deliveries/{uuid}', [DeliveryController::class, 'destroy']);
    Route::put('/deliveries/{uuid}/status', [DeliveryController::class, 'updateStatus']);
    Route::post('/deliveries/{uuid}/proof', [DeliveryController::class, 'uploadProof']);
    Route::get('/deliveries/{uuid}/proof/download', [DeliveryController::class, 'downloadProof']);
    Route::get('/deliveries/{uuid}/receipt', [DeliveryController::class, 'receipt']);
    Route::get('/deliveries/{uuid}/receipt/pdf', [DeliveryController::class, 'receiptPdf']);
    Route::post('/deliveries/{uuid}/assign-driver', [DeliveryController::class, 'assignDriver']);

    // Reports & Analytics API
    Route::prefix('reports')->group(function () {
        // Sales Reports
        Route::get('/sales/daily', [ReportController::class, 'dailySales']);
        Route::get('/sales/summary', [ReportController::class, 'salesSummary']);
        Route::get('/sales/by-category', [ReportController::class, 'salesByCategory']);
        Route::get('/sales/by-customer', [ReportController::class, 'salesByCustomer']);
        Route::get('/sales/by-payment-method', [ReportController::class, 'salesByPaymentMethod']);
        Route::get('/sales/by-cashier', [ReportController::class, 'salesByCashier']);

        // Inventory Reports
        Route::get('/inventory/valuation', [ReportController::class, 'inventoryValuation']);
        Route::get('/inventory/movement', [ReportController::class, 'stockMovement']);
        Route::get('/inventory/low-stock', [ReportController::class, 'lowStock']);
        Route::get('/inventory/dead-stock', [ReportController::class, 'deadStock']);
        Route::get('/inventory/profitability', [ReportController::class, 'productProfitability']);

        // Credit Reports
        Route::get('/credit/aging', [ReportController::class, 'creditAging']);
        Route::get('/credit/collection', [ReportController::class, 'collectionReport']);

        // Purchase Reports
        Route::get('/purchases/by-supplier', [ReportController::class, 'purchasesBySupplier']);
        Route::get('/purchases/price-comparison', [ReportController::class, 'priceComparison']);
    });

    // Settings & Configuration API
    Route::prefix('settings')->group(function () {
        // Store Profile
        Route::get('/store', [SettingsController::class, 'getStoreProfile']);
        Route::put('/store', [SettingsController::class, 'updateStoreProfile']);
        Route::post('/store/logo', [SettingsController::class, 'uploadStoreLogo']);
        Route::delete('/store/logo', [SettingsController::class, 'deleteStoreLogo']);

        // User Management
        Route::get('/users', [SettingsController::class, 'listUsers']);
        Route::post('/users', [SettingsController::class, 'createUser']);
        Route::get('/users/{uuid}', [SettingsController::class, 'getUserDetails']);
        Route::put('/users/{uuid}', [SettingsController::class, 'updateUser']);
        Route::post('/users/{uuid}/deactivate', [SettingsController::class, 'deactivateUser']);
        Route::post('/users/{uuid}/activate', [SettingsController::class, 'activateUser']);
        Route::delete('/users/{uuid}', [SettingsController::class, 'deleteUser']);
        Route::post('/users/{uuid}/reset-password', [SettingsController::class, 'resetUserPassword']);

        // Branch Management
        Route::get('/branches', [SettingsController::class, 'listBranches']);
        Route::post('/branches', [SettingsController::class, 'createBranch']);
        Route::put('/branches/{uuid}', [SettingsController::class, 'updateBranch']);
        Route::delete('/branches/{uuid}', [SettingsController::class, 'deleteBranch']);

        // Permission Management
        Route::get('/permissions', [SettingsController::class, 'getPermissions']);
        Route::get('/permissions/user/{userId}', [SettingsController::class, 'getUserPermissions']);
        Route::put('/permissions/user/{userId}', [SettingsController::class, 'updateUserPermissions']);
        Route::get('/permissions/role/{role}', [SettingsController::class, 'getRolePermissions']);
        Route::put('/permissions/role/{role}', [SettingsController::class, 'updateRolePermissions']);

        // Payment Methods
        Route::get('/payment-methods', [SettingsController::class, 'getPaymentMethods']);
        Route::put('/payment-methods', [SettingsController::class, 'updatePaymentMethods']);

        // Receipt Template
        Route::get('/receipt-template', [SettingsController::class, 'getReceiptTemplate']);
        Route::put('/receipt-template', [SettingsController::class, 'updateReceiptTemplate']);
        Route::get('/receipt-template/preview', [SettingsController::class, 'previewReceipt']);

        // Tax Settings
        Route::get('/tax', [SettingsController::class, 'getTaxSettings']);
        Route::put('/tax', [SettingsController::class, 'updateTaxSettings']);

        // Credit Settings
        Route::get('/credit', [SettingsController::class, 'getCreditSettings']);
        Route::put('/credit', [SettingsController::class, 'updateCreditSettings']);

        // System Settings
        Route::get('/system', [SettingsController::class, 'getSystemSettings']);
        Route::put('/system', [SettingsController::class, 'updateSystemSettings']);
        Route::post('/system/clear-cache', [SettingsController::class, 'clearCache']);
    });

    // =========================================================================
    // MPC: Share Capital Module
    // =========================================================================
    Route::prefix('share-capital')->group(function () {
        Route::get('/overview', [ShareCapitalController::class, 'overview']);
        Route::post('/compute-isc', [ShareCapitalController::class, 'computeISC']);
        Route::get('/', [ShareCapitalController::class, 'index']);
        Route::post('/', [ShareCapitalController::class, 'store']);
        Route::get('/{uuid}', [ShareCapitalController::class, 'show']);
        Route::put('/{uuid}', [ShareCapitalController::class, 'update']);
        Route::post('/{uuid}/payments', [ShareCapitalController::class, 'recordPayment']);
        Route::get('/{uuid}/payments', [ShareCapitalController::class, 'listPayments']);
        Route::delete('/{uuid}/payments/{payUuid}', [ShareCapitalController::class, 'reversePayment']);
        Route::post('/{uuid}/certificates', [ShareCapitalController::class, 'issueCertificate']);
        Route::get('/{uuid}/certificates', [ShareCapitalController::class, 'listCertificates']);
        Route::delete('/{uuid}/certificates/{certUuid}', [ShareCapitalController::class, 'cancelCertificate']);
        Route::get('/{uuid}/statement', [ShareCapitalController::class, 'statement']);
        Route::post('/{uuid}/withdraw', [ShareCapitalController::class, 'withdraw']);
    });

    // =========================================================================
    // MPC: Loan Products (configuration)
    // =========================================================================
    Route::prefix('loan-products')->group(function () {
        Route::get('/', [LoanProductController::class, 'index']);
        Route::post('/', [LoanProductController::class, 'store']);
        Route::get('/{uuid}', [LoanProductController::class, 'show']);
        Route::put('/{uuid}', [LoanProductController::class, 'update']);
        Route::delete('/{uuid}', [LoanProductController::class, 'destroy']);
    });

    // =========================================================================
    // MPC: Loans / Lending Module
    // =========================================================================
    Route::prefix('loans')->group(function () {
        // Static sub-routes must appear BEFORE /{uuid} to avoid being caught as uuid values
        Route::get('/overview', [LoanController::class, 'overview']);
        Route::get('/delinquent', [LoanController::class, 'delinquent']);
        Route::get('/aging', [LoanController::class, 'aging']);
        Route::post('/amortization/preview', [LoanController::class, 'previewAmortization']);
        Route::get('/', [LoanController::class, 'index']);
        Route::post('/', [LoanController::class, 'store']);
        Route::get('/{uuid}', [LoanController::class, 'show']);
        Route::put('/{uuid}', [LoanController::class, 'update']);
        Route::post('/{uuid}/approve', [LoanController::class, 'approve']);
        Route::post('/{uuid}/reject', [LoanController::class, 'reject']);
        Route::post('/{uuid}/disburse', [LoanController::class, 'disburse']);
        Route::post('/{uuid}/payments', [LoanController::class, 'recordPayment']);
        Route::get('/{uuid}/payments', [LoanController::class, 'listPayments']);
        Route::delete('/{uuid}/payments/{payUuid}', [LoanController::class, 'reversePayment']);
        Route::get('/{uuid}/schedule', [LoanController::class, 'schedule']);
        Route::get('/{uuid}/statement', [LoanController::class, 'statement']);
        Route::post('/{uuid}/penalties/compute', [LoanController::class, 'computePenalties']);
        Route::post('/{uuid}/penalties/{penUuid}/waive', [LoanController::class, 'waivePenalty']);
    });

    // =========================================================================
    // MPC: Member Savings (Voluntary + Compulsory)
    // =========================================================================
    Route::prefix('savings')->group(function () {
        Route::get('/overview', [SavingsController::class, 'overview']);
        Route::post('/batch-credit-interest', [SavingsController::class, 'batchCreditInterest']);
        Route::get('/', [SavingsController::class, 'index']);
        Route::post('/', [SavingsController::class, 'store']);
        Route::get('/{uuid}', [SavingsController::class, 'show']);
        Route::put('/{uuid}', [SavingsController::class, 'update']);
        Route::post('/{uuid}/deposit', [SavingsController::class, 'deposit']);
        Route::post('/{uuid}/withdraw', [SavingsController::class, 'withdraw']);
        Route::get('/{uuid}/transactions', [SavingsController::class, 'listTransactions']);
        Route::delete('/{uuid}/transactions/{txUuid}', [SavingsController::class, 'reverseTransaction']);
        Route::get('/{uuid}/statement', [SavingsController::class, 'statement']);
        Route::post('/{uuid}/close', [SavingsController::class, 'close']);
    });

    // =========================================================================
    // MPC: Time Deposits
    // =========================================================================
    Route::prefix('time-deposits')->group(function () {
        Route::get('/overview', [TimeDepositController::class, 'overview']);
        Route::post('/interest-preview', [TimeDepositController::class, 'interestPreview']);
        Route::get('/', [TimeDepositController::class, 'index']);
        Route::post('/', [TimeDepositController::class, 'store']);
        Route::get('/{uuid}', [TimeDepositController::class, 'show']);
        Route::post('/{uuid}/accrue', [TimeDepositController::class, 'accrueInterest']);
        Route::post('/{uuid}/mature', [TimeDepositController::class, 'mature']);
        Route::post('/{uuid}/pre-terminate', [TimeDepositController::class, 'preTerminate']);
        Route::post('/{uuid}/rollover', [TimeDepositController::class, 'rollOver']);
        Route::get('/{uuid}/transactions', [TimeDepositController::class, 'listTransactions']);
        Route::get('/{uuid}/statement', [TimeDepositController::class, 'statement']);
    });

    // =========================================================================
    // MPC: Patronage Refund Computation
    // =========================================================================
    Route::prefix('patronage-refunds')->group(function () {
        Route::get('/overview', [PatronageRefundController::class, 'overview']);
        Route::get('/', [PatronageRefundController::class, 'index']);
        Route::post('/', [PatronageRefundController::class, 'store']);
        Route::get('/{uuid}', [PatronageRefundController::class, 'show']);
        Route::get('/{uuid}/summary', [PatronageRefundController::class, 'summary']);
        Route::post('/{uuid}/compute', [PatronageRefundController::class, 'compute']);
        Route::post('/{uuid}/approve', [PatronageRefundController::class, 'approve']);
        Route::get('/{uuid}/allocations', [PatronageRefundController::class, 'allocations']);
        Route::post('/{uuid}/allocations/{allocUuid}/pay', [PatronageRefundController::class, 'pay']);
        Route::post('/{uuid}/allocations/{allocUuid}/forfeit', [PatronageRefundController::class, 'forfeit']);
    });

    // =========================================================================
    // MPC: Member Enrollment / Lifecycle
    // =========================================================================
    Route::prefix('memberships')->group(function () {
        Route::get('/overview', [MembershipController::class, 'overview']);

        // Applications
        Route::get('/applications', [MembershipController::class, 'indexApplications']);
        Route::post('/applications', [MembershipController::class, 'submitApplication']);
        Route::get('/applications/{uuid}', [MembershipController::class, 'showApplication']);
        Route::post('/applications/{uuid}/approve', [MembershipController::class, 'approveApplication']);
        Route::post('/applications/{uuid}/reject', [MembershipController::class, 'rejectApplication']);

        // Member status transitions
        Route::get('/members', [MembershipController::class, 'indexMembers']);
        Route::post('/members/{uuid}/deactivate', [MembershipController::class, 'deactivate']);
        Route::post('/members/{uuid}/reinstate', [MembershipController::class, 'reinstate']);
        Route::post('/members/{uuid}/expel', [MembershipController::class, 'expel']);
        Route::post('/members/{uuid}/resign', [MembershipController::class, 'resign']);

        // Fees
        Route::get('/fees', [MembershipController::class, 'indexFees']);
        Route::post('/fees', [MembershipController::class, 'recordFee']);
        Route::delete('/fees/{uuid}', [MembershipController::class, 'reverseFee']);
    });

    // =========================================================================
    // MPC: CDA Compliance Reporting
    // =========================================================================
    Route::prefix('cda')->group(function () {
        Route::get('/overview', [CdaComplianceController::class, 'overview']);

        // Annual Reports
        Route::get('/reports', [CdaComplianceController::class, 'indexReports']);
        Route::post('/reports/compile', [CdaComplianceController::class, 'compile']);
        Route::get('/reports/{uuid}', [CdaComplianceController::class, 'showReport']);
        Route::put('/reports/{uuid}', [CdaComplianceController::class, 'updateReport']);
        Route::post('/reports/{uuid}/finalize', [CdaComplianceController::class, 'finalizeReport']);
        Route::post('/reports/{uuid}/mark-submitted', [CdaComplianceController::class, 'markSubmitted']);
        Route::get('/reports/{uuid}/statistical-data', [CdaComplianceController::class, 'statisticalData']);

        // Annual General Assembly records
        Route::get('/aga', [CdaComplianceController::class, 'indexAga']);
        Route::post('/aga', [CdaComplianceController::class, 'storeAga']);
        Route::get('/aga/{uuid}', [CdaComplianceController::class, 'showAga']);
        Route::put('/aga/{uuid}', [CdaComplianceController::class, 'updateAga']);
        Route::delete('/aga/{uuid}', [CdaComplianceController::class, 'destroyAga']);
        Route::post('/aga/{uuid}/finalize', [CdaComplianceController::class, 'finalizeAga']);

        // Officers / Board of Directors
        Route::get('/officers', [CdaComplianceController::class, 'indexOfficers']);
        Route::post('/officers', [CdaComplianceController::class, 'storeOfficer']);
        Route::get('/officers/{uuid}', [CdaComplianceController::class, 'showOfficer']);
        Route::put('/officers/{uuid}', [CdaComplianceController::class, 'updateOfficer']);
        Route::delete('/officers/{uuid}', [CdaComplianceController::class, 'destroyOfficer']);
    });

    // =========================================================================
    // MPC: Mutual Aid Fund (MAF)
    // =========================================================================
    Route::prefix('maf')->group(function () {
        // Fund overview and reporting
        Route::get('/overview',      [MafController::class, 'fundOverview']);
        Route::get('/claims-report', [MafController::class, 'claimsReport']);

        // Benefit programs (admin configuration)
        Route::get('/',         [MafController::class, 'programIndex']);
        Route::post('/',        [MafController::class, 'programStore']);
        Route::get('/{uuid}',   [MafController::class, 'programShow']);
        Route::put('/{uuid}',   [MafController::class, 'programUpdate']);
        Route::delete('/{uuid}',[MafController::class, 'programDestroy']);

        // Contributions (store-wide listing and recording)
        Route::get('/contributions',                 [MafController::class, 'contributionIndex']);
        Route::post('/contributions',                [MafController::class, 'contributionStore']);
        Route::post('/contributions/{uuid}/reverse', [MafController::class, 'contributionReverse']);

        // Claims lifecycle
        Route::get('/claims',                  [MafController::class, 'claimIndex']);
        Route::post('/claims',                 [MafController::class, 'claimStore']);
        Route::get('/claims/{uuid}',           [MafController::class, 'claimShow']);
        Route::post('/claims/{uuid}/review',   [MafController::class, 'claimReview']);
        Route::post('/claims/{uuid}/approve',  [MafController::class, 'claimApprove']);
        Route::post('/claims/{uuid}/reject',   [MafController::class, 'claimReject']);
        Route::post('/claims/{uuid}/pay',      [MafController::class, 'claimPay']);
    });

    // MAF member-scoped routes (nested under /customers/{uuid})
    Route::prefix('customers/{uuid}')->group(function () {
        Route::get('/maf-contributions',                          [MafController::class, 'memberContributions']);
        Route::get('/maf-claims',                                 [MafController::class, 'memberClaims']);
        Route::get('/maf-beneficiaries',                          [MafController::class, 'beneficiaryIndex']);
        Route::post('/maf-beneficiaries',                         [MafController::class, 'beneficiaryStore']);
        Route::put('/maf-beneficiaries/{bUuid}',                  [MafController::class, 'beneficiaryUpdate']);
        Route::post('/maf-beneficiaries/{bUuid}/deactivate',      [MafController::class, 'beneficiaryDeactivate']);
    });
});
