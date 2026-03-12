<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoicePaymentController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\HsnCodeController;
use App\Http\Controllers\Api\ClientLedgerController;
use App\Http\Controllers\Api\GstController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\VendorPaymentController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\SalesReturnController;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'userProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/password-reset', [AuthController::class, 'sendPasswordResetLink']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/users/list', [AuthController::class, 'getAllUsers']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        
        //Budgets routes
        Route::get('/budgets', [BudgetController::class, 'index']);
        Route::post('/budgets-create', [BudgetController::class, 'store']);
        Route::get('/budgets/{id}', [BudgetController::class, 'show']);
        Route::put('/budgets/{id}', [BudgetController::class, 'update']);
        Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);
        Route::get('/my-loans', [BudgetController::class, 'myLoans']);

        //Transactions routes
        Route::post('/transactions/create', [TransactionController::class, 'createTransaction']);
        Route::get('/transactions', [TransactionController::class, 'getTransactions']);
        Route::put('/transactions/{id}', [TransactionController::class, 'updateTransaction']);
        Route::delete('/transactions/{id}', [TransactionController::class, 'deleteTransaction']);

        // Categories Routes
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories/create', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Accounts Routes
        Route::get('/accounts', [AccountController::class, 'index']);
        Route::post('/accounts/create', [AccountController::class, 'store']);
        Route::get('/accounts/{id}', [AccountController::class, 'show']);
        Route::put('/accounts/{id}', [AccountController::class, 'update']);
        Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);
        // Clients Routes
        Route::apiResource('clients', ClientController::class);

        // Invoices Routes
        Route::apiResource('invoices', InvoiceController::class);

        // Extra Invoice Routes
        Route::get('invoices/next-number/generate', [InvoiceController::class, 'getNextInvoiceNumber']);
        Route::get('invoices/client/{clientId}', [InvoiceController::class, 'getByClient']);


    // Invoice Payment Routes
        Route::apiResource('invoice-payments', InvoicePaymentController::class);
        Route::get('invoice-payments/invoice/{invoiceId}', [InvoicePaymentController::class, 'getByInvoice']);


        // Company Routes
        Route::apiResource('companies', CompanyController::class);
        
        // HSN Code Routes
        Route::apiResource('hsn-codes', HsnCodeController::class);

        // Client Ledger Route
        Route::get('clients/{clientId}/ledger', [ClientLedgerController::class, 'show']);

        Route::prefix('gst')->group(function () {

        // State codes — dropdown ke liye
        Route::get('/states', [GstController::class, 'states']);

        // Reports
        Route::get('/summary/{period}',     [GstController::class, 'summary']);
        Route::get('/gstr1/{period}',       [GstController::class, 'gstr1']);
        Route::get('/gstr3b/{period}',      [GstController::class, 'gstr3b']);
        Route::get('/hsn-summary/{period}', [GstController::class, 'hsnSummary']);

        // Returns management
        Route::get('/returns',              [GstController::class, 'index']);
        Route::post('/returns/draft',       [GstController::class, 'saveDraft']);
        Route::post('/returns/{id}/file',   [GstController::class, 'fileReturn']);
    });


    // ── Vendors ──────────────────────────────────────────────
    Route::prefix('vendors')->group(function () {
        Route::get('/summary',    [VendorController::class, 'summary']);
        Route::get('/',           [VendorController::class, 'index']);
        Route::post('/',          [VendorController::class, 'store']);
        Route::get('/{id}',       [VendorController::class, 'show']);
        Route::put('/{id}',       [VendorController::class, 'update']);
        Route::delete('/{id}',    [VendorController::class, 'destroy']);
    });

    // ── Purchase Orders ───────────────────────────────────────
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/summary',          [PurchaseOrderController::class, 'summary']);
        Route::get('/',                 [PurchaseOrderController::class, 'index']);
        Route::post('/',                [PurchaseOrderController::class, 'store']);
        Route::get('/{id}',             [PurchaseOrderController::class, 'show']);
        Route::put('/{id}',             [PurchaseOrderController::class, 'update']);
        Route::delete('/{id}',          [PurchaseOrderController::class, 'destroy']);
        Route::post('/{id}/status',     [PurchaseOrderController::class, 'updateStatus']);
    });

    // ── Vendor Payments ───────────────────────────────────────
    Route::prefix('vendor-payments')->group(function () {
        Route::get('/',        [VendorPaymentController::class, 'index']);
        Route::post('/',       [VendorPaymentController::class, 'store']);
        Route::delete('/{id}', [VendorPaymentController::class, 'destroy']);
    });

    // ── Product Categories ────────────────────────────────────
    Route::prefix('product-categories')->group(function () {
        Route::get('/',        [ProductCategoryController::class, 'index']);
        Route::post('/',       [ProductCategoryController::class, 'store']);
        Route::get('/{id}',    [ProductCategoryController::class, 'show']);
        Route::put('/{id}',    [ProductCategoryController::class, 'update']);
        Route::delete('/{id}', [ProductCategoryController::class, 'destroy']);
    });

    // ── Products ──────────────────────────────────────────────
    Route::prefix('products')->group(function () {
        Route::get('/summary',   [ProductController::class, 'summary']);
        Route::get('/low-stock', [ProductController::class, 'lowStock']);
        Route::get('/',          [ProductController::class, 'index']);
        Route::post('/',         [ProductController::class, 'store']);
        Route::get('/{id}',      [ProductController::class, 'show']);
        Route::put('/{id}',      [ProductController::class, 'update']);
        Route::delete('/{id}',   [ProductController::class, 'destroy']);
    });

    // ── Stock Movements ───────────────────────────────────────
    Route::prefix('stock-movements')->group(function () {
        Route::get('/report',              [StockMovementController::class, 'report']);
        Route::get('/by-product/{id}',     [StockMovementController::class, 'byProduct']);
        Route::get('/',                    [StockMovementController::class, 'index']);
        Route::post('/',                   [StockMovementController::class, 'store']);
        Route::delete('/{id}',             [StockMovementController::class, 'destroy']);
    });

   // Sales Returns
Route::prefix('sales-returns')->group(function () {
    Route::get('/', [SalesReturnController::class, 'index']);
    Route::post('/', [SalesReturnController::class, 'store']);
    Route::get('/by-invoice/{invoiceId}', [SalesReturnController::class, 'getByInvoice']); // ✅ pehle
    Route::patch('/{id}/status', [SalesReturnController::class, 'updateStatus']);           // ✅ pehle
    Route::get('/{id}', [SalesReturnController::class, 'show']);                            // ✅ last
});

// Purchase Returns
Route::prefix('purchase-returns')->group(function () {
    Route::get('/', [PurchaseReturnController::class, 'index']);
    Route::post('/', [PurchaseReturnController::class, 'store']);
    Route::get('/by-po/{poId}', [PurchaseReturnController::class, 'getByPO']);  // ✅ pehle
    Route::patch('/{id}/status', [PurchaseReturnController::class, 'updateStatus']); // ✅ pehle
    Route::get('/{id}', [PurchaseReturnController::class, 'show']);             // ✅ last
});
    
});
