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
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\OrganisationController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\AIController;


// ── Public Routes ─────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
Route::post('/password-reset', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:password-reset');

Route::post('/ask-ai', [AIController::class, 'askAI']);


Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────
    Route::get('/me', [AuthController::class, 'userProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users/list', [AuthController::class, 'getAllUsers']);

    // ── Organisation — sabhi authenticated users ke liye ──
    Route::prefix('organisation')->group(function () {
        Route::post('/create',                   [OrganisationController::class, 'create']);
        Route::get('/',                          [OrganisationController::class, 'show']);
        Route::put('/',                          [OrganisationController::class, 'update']);
        Route::get('/members',                   [OrganisationController::class, 'members']);
        Route::post('/members',                  [OrganisationController::class, 'addMember']);
        Route::put('/members/{userId}/role',     [OrganisationController::class, 'updateMemberRole']);
        Route::patch('/members/{userId}/toggle', [OrganisationController::class, 'toggleMember']);
        Route::delete('/members/{userId}',       [OrganisationController::class, 'removeMember']);
    });

    // ── User Management (RBAC) ────────────────────────────
    Route::prefix('users')->group(function () {
        Route::get('/',                     [UserController::class, 'index']);
        Route::post('/',                    [UserController::class, 'store']);
        Route::get('/{id}',                 [UserController::class, 'show']);
        Route::put('/{id}',                 [UserController::class, 'update']);
        Route::patch('/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::delete('/{id}',              [UserController::class, 'destroy']);
    });

    // ── Roles & Permissions ───────────────────────────────
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::prefix('roles')->group(function () {
        Route::get('/',                 [RoleController::class, 'index']);
        Route::get('/{id}',             [RoleController::class, 'show']);
        Route::put('/{id}/permissions', [RoleController::class, 'updatePermissions']);
    });

    // ══════════════════════════════════════════════════════
    // PERSONAL ROUTES — org nahi chahiye
    // ══════════════════════════════════════════════════════

    // ── Budgets ───────────────────────────────────────────
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets-create', [BudgetController::class, 'store']);
    Route::get('/budgets/{id}', [BudgetController::class, 'show']);
    Route::put('/budgets/{id}', [BudgetController::class, 'update']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);
    Route::get('/my-loans', [BudgetController::class, 'myLoans']);

    // ── Transactions ──────────────────────────────────────
    Route::post('/transactions/create', [TransactionController::class, 'createTransaction']);
    Route::get('/transactions', [TransactionController::class, 'getTransactions']);
    Route::put('/transactions/{id}', [TransactionController::class, 'updateTransaction']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'deleteTransaction']);

    // ── Categories (personal) ─────────────────────────────
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories/create', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // ── Accounts ──────────────────────────────────────────
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts/create', [AccountController::class, 'store']);
    Route::get('/accounts/{id}', [AccountController::class, 'show']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);
    Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);

    // ══════════════════════════════════════════════════════
    // ORG ROUTES — org.access middleware required
    // Sirf org_owner / org_member access kar sakte hain
    // ══════════════════════════════════════════════════════
    Route::middleware(['org.access', 'tenant.org'])->group(function () {

        // ── HSN Codes ─────────────────────────────────────
        Route::apiResource('hsn-codes', HsnCodeController::class);

        // ── Companies ─────────────────────────────────────
        Route::apiResource('companies', CompanyController::class)->middleware([
            'index'   => 'permission:companies.view',
            'show'    => 'permission:companies.view',
            'store'   => 'permission:companies.create',
            'update'  => 'permission:companies.edit',
            'destroy' => 'permission:companies.delete',
        ]);

        // ── Clients ───────────────────────────────────────
        Route::apiResource('clients', ClientController::class)->middleware([
            'index'   => 'permission:clients.view',
            'show'    => 'permission:clients.view',
            'store'   => 'permission:clients.create',
            'update'  => 'permission:clients.edit',
            'destroy' => 'permission:clients.delete',
        ]);
        Route::get('clients/{clientId}/ledger', [ClientLedgerController::class, 'show'])
            ->middleware('permission:clients.view');

        // ── Invoices ──────────────────────────────────────
        Route::apiResource('invoices', InvoiceController::class)->middleware([
            'index'   => 'permission:invoices.view',
            'show'    => 'permission:invoices.view',
            'store'   => 'permission:invoices.create',
            'update'  => 'permission:invoices.edit',
            'destroy' => 'permission:invoices.delete',
        ]);
        Route::get('invoices/next-number/generate', [InvoiceController::class, 'getNextInvoiceNumber'])
            ->middleware('permission:invoices.view');
        Route::get('invoices/client/{clientId}', [InvoiceController::class, 'getByClient'])
            ->middleware('permission:invoices.view');

        // ── Invoice Payments ──────────────────────────────
        Route::apiResource('invoice-payments', InvoicePaymentController::class)->middleware([
            'index'   => 'permission:invoice_payments.view',
            'show'    => 'permission:invoice_payments.view',
            'store'   => 'permission:invoice_payments.create',
            'update'  => 'permission:invoice_payments.edit',
            'destroy' => 'permission:invoice_payments.delete',
        ]);
        Route::get('invoice-payments/invoice/{invoiceId}', [InvoicePaymentController::class, 'getByInvoice'])
            ->middleware('permission:invoice_payments.view');

        // ── GST ───────────────────────────────────────────
        Route::prefix('gst')->group(function () {
            Route::get('/states',               [GstController::class, 'states'])->middleware('permission:gst.view');
            Route::get('/summary/{period}',     [GstController::class, 'summary'])->middleware('permission:gst.view');
            Route::get('/gstr1/{period}',       [GstController::class, 'gstr1'])->middleware('permission:gst.view');
            Route::get('/gstr3b/{period}',      [GstController::class, 'gstr3b'])->middleware('permission:gst.view');
            Route::get('/hsn-summary/{period}', [GstController::class, 'hsnSummary'])->middleware('permission:gst.view');
            Route::get('/returns',              [GstController::class, 'index'])->middleware('permission:gst.view');
            Route::post('/returns/draft',       [GstController::class, 'saveDraft'])->middleware('permission:gst.manage');
            Route::post('/returns/{id}/file',   [GstController::class, 'fileReturn'])->middleware('permission:gst.manage');
        });

        // ── Vendors ───────────────────────────────────────
        Route::prefix('vendors')->group(function () {
            Route::get('/summary', [VendorController::class, 'summary'])->middleware('permission:vendors.view');
            Route::get('/',        [VendorController::class, 'index'])->middleware('permission:vendors.view');
            Route::post('/',       [VendorController::class, 'store'])->middleware('permission:vendors.create');
            Route::get('/{id}',    [VendorController::class, 'show'])->middleware('permission:vendors.view');
            Route::put('/{id}',    [VendorController::class, 'update'])->middleware('permission:vendors.edit');
            Route::delete('/{id}', [VendorController::class, 'destroy'])->middleware('permission:vendors.delete');
        });

        // ── Vendor Payments ───────────────────────────────
        Route::prefix('vendor-payments')->group(function () {
            Route::get('/',        [VendorPaymentController::class, 'index'])->middleware('permission:vendor_payments.view');
            Route::post('/',       [VendorPaymentController::class, 'store'])->middleware('permission:vendor_payments.create');
            Route::delete('/{id}', [VendorPaymentController::class, 'destroy'])->middleware('permission:vendor_payments.delete');
        });

        // ── Purchase Orders ───────────────────────────────
        Route::prefix('purchase-orders')->group(function () {
            Route::get('/summary',      [PurchaseOrderController::class, 'summary'])->middleware('permission:purchase_orders.view');
            Route::get('/',             [PurchaseOrderController::class, 'index'])->middleware('permission:purchase_orders.view');
            Route::post('/',            [PurchaseOrderController::class, 'store'])->middleware('permission:purchase_orders.create');
            Route::get('/{id}',         [PurchaseOrderController::class, 'show'])->middleware('permission:purchase_orders.view');
            Route::put('/{id}',         [PurchaseOrderController::class, 'update'])->middleware('permission:purchase_orders.edit');
            Route::delete('/{id}',      [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchase_orders.delete');
            Route::post('/{id}/status', [PurchaseOrderController::class, 'updateStatus'])->middleware('permission:purchase_orders.edit');
        });

        // ── Sales Returns ─────────────────────────────────
        Route::prefix('sales-returns')->group(function () {
            Route::get('/',                       [SalesReturnController::class, 'index'])->middleware('permission:sales_returns.view');
            Route::post('/',                      [SalesReturnController::class, 'store'])->middleware('permission:sales_returns.create');
            Route::get('/by-invoice/{invoiceId}', [SalesReturnController::class, 'getByInvoice'])->middleware('permission:sales_returns.view');
            Route::patch('/{id}/status',          [SalesReturnController::class, 'updateStatus'])->middleware('permission:sales_returns.edit');
            Route::get('/{id}',                   [SalesReturnController::class, 'show'])->middleware('permission:sales_returns.view');
        });

        // ── Purchase Returns ──────────────────────────────
        Route::prefix('purchase-returns')->group(function () {
            Route::get('/',             [PurchaseReturnController::class, 'index'])->middleware('permission:purchase_returns.view');
            Route::post('/',            [PurchaseReturnController::class, 'store'])->middleware('permission:purchase_returns.create');
            Route::get('/by-po/{poId}', [PurchaseReturnController::class, 'getByPO'])->middleware('permission:purchase_returns.view');
            Route::patch('/{id}/status',[PurchaseReturnController::class, 'updateStatus'])->middleware('permission:purchase_returns.edit');
            Route::get('/{id}',         [PurchaseReturnController::class, 'show'])->middleware('permission:purchase_returns.view');
        });

        // ── Product Categories ────────────────────────────
        Route::prefix('product-categories')->group(function () {
            Route::get('/',        [ProductCategoryController::class, 'index'])->middleware('permission:product_categories.view');
            Route::post('/',       [ProductCategoryController::class, 'store'])->middleware('permission:product_categories.create');
            Route::get('/{id}',    [ProductCategoryController::class, 'show'])->middleware('permission:product_categories.view');
            Route::put('/{id}',    [ProductCategoryController::class, 'update'])->middleware('permission:product_categories.edit');
            Route::delete('/{id}', [ProductCategoryController::class, 'destroy'])->middleware('permission:product_categories.delete');
        });

        // ── Products ──────────────────────────────────────
        Route::prefix('products')->group(function () {
            Route::get('/summary',   [ProductController::class, 'summary'])->middleware('permission:products.view');
            Route::get('/low-stock', [ProductController::class, 'lowStock'])->middleware('permission:products.view');
            Route::get('/',          [ProductController::class, 'index'])->middleware('permission:products.view');
            Route::post('/',         [ProductController::class, 'store'])->middleware('permission:products.create');
            Route::get('/{id}',      [ProductController::class, 'show'])->middleware('permission:products.view');
            Route::put('/{id}',      [ProductController::class, 'update'])->middleware('permission:products.edit');
            Route::delete('/{id}',   [ProductController::class, 'destroy'])->middleware('permission:products.delete');
        });

        // ── Stock Movements ───────────────────────────────
        Route::prefix('stock-movements')->group(function () {
            Route::get('/report',          [StockMovementController::class, 'report'])->middleware('permission:stock_movements.view');
            Route::get('/by-product/{id}', [StockMovementController::class, 'byProduct'])->middleware('permission:stock_movements.view');
            Route::get('/',                [StockMovementController::class, 'index'])->middleware('permission:stock_movements.view');
            Route::post('/',               [StockMovementController::class, 'store'])->middleware('permission:stock_movements.create');
            Route::delete('/{id}',         [StockMovementController::class, 'destroy'])->middleware('permission:stock_movements.delete');
        });

        // ── Attributes ────────────────────────────────────
        Route::get('/attribute-groups',                       [AttributeController::class, 'indexGroups'])->middleware('permission:products.view');
        Route::post('/attribute-groups',                      [AttributeController::class, 'storeGroup'])->middleware('permission:products.edit');
        Route::put('/attribute-groups/{id}',                  [AttributeController::class, 'updateGroup'])->middleware('permission:products.edit');
        Route::delete('/attribute-groups/{id}',               [AttributeController::class, 'destroyGroup'])->middleware('permission:products.delete');
        Route::post('/attribute-groups/{groupId}/attributes', [AttributeController::class, 'storeAttribute'])->middleware('permission:products.edit');
        Route::put('/attributes/{id}',                        [AttributeController::class, 'updateAttribute'])->middleware('permission:products.edit');
        Route::delete('/attributes/{id}',                     [AttributeController::class, 'destroyAttribute'])->middleware('permission:products.delete');
        Route::get('/products/{productId}/attributes',        [AttributeController::class, 'getProductAttributes'])->middleware('permission:products.view');
        Route::post('/products/{productId}/attributes',       [AttributeController::class, 'saveProductAttributes'])->middleware('permission:products.edit');

        // ── CRM — Leads ───────────────────────────────────
        Route::prefix('leads')->group(function () {
            Route::get('/summary',          [LeadController::class, 'summary']);
            Route::get('/pipeline',         [LeadController::class, 'pipeline']);
            Route::get('/',                 [LeadController::class, 'index']);
            Route::post('/',                [LeadController::class, 'store']);
            Route::get('/{id}',             [LeadController::class, 'show']);
            Route::put('/{id}',             [LeadController::class, 'update']);
            Route::delete('/{id}',          [LeadController::class, 'destroy']);
            Route::patch('/{id}/status',    [LeadController::class, 'updateStatus']);
            Route::post('/{id}/activities', [LeadController::class, 'addActivity']);
            Route::post('/{id}/follow-ups', [LeadController::class, 'addFollowUp']);
            Route::get('/{id}/products',    [LeadController::class, 'products']);
            Route::post('/{id}/products',   [LeadController::class, 'addProduct']);
            Route::put('/{id}/products/{leadProductId}', [LeadController::class, 'updateProduct']);
            Route::delete('/{id}/products/{leadProductId}', [LeadController::class, 'deleteProduct']);
            Route::post('/{id}/quotation',  [QuotationController::class, 'storeFromLead'])->middleware('permission:quotations.create');
            Route::post('/{id}/link-po',      [LeadController::class, 'linkPO']);
            Route::post('/{id}/link-invoice', [LeadController::class, 'linkInvoice']);
        });
        Route::patch('/follow-ups/{id}/done', [LeadController::class, 'markFollowUpDone']);

        // ── CRM — Campaigns ───────────────────────────────
        Route::prefix('campaigns')->group(function () {
            Route::get('/',                      [CampaignController::class, 'index'])->middleware('permission:campaigns.view');
            Route::post('/',                     [CampaignController::class, 'store'])->middleware('permission:campaigns.create');
            Route::get('/{id}',                  [CampaignController::class, 'show'])->middleware('permission:campaigns.view');
            Route::put('/{id}',                  [CampaignController::class, 'update'])->middleware('permission:campaigns.edit');
            Route::delete('/{id}',               [CampaignController::class, 'destroy'])->middleware('permission:campaigns.delete');
            Route::post('/{id}/leads',           [CampaignController::class, 'attachLeads'])->middleware('permission:campaigns.edit');
            Route::delete('/{id}/leads/{leadId}',[CampaignController::class, 'detachLead'])->middleware('permission:campaigns.edit');
        });

        Route::prefix('exports')->group(function () {
            Route::post('/', [ExportController::class, 'store'])->middleware('permission:exports.create');
            Route::get('/{id}', [ExportController::class, 'show'])->middleware('permission:exports.view');
            Route::get('/{id}/download', [ExportController::class, 'download'])->middleware('permission:exports.view');
        });

        Route::prefix('quotations')->group(function () {
            Route::get('/', [QuotationController::class, 'index'])->middleware('permission:quotations.view');
            Route::post('/', [QuotationController::class, 'store'])->middleware('permission:quotations.create');
            Route::get('/{id}', [QuotationController::class, 'show'])->middleware('permission:quotations.view');
            Route::put('/{id}', [QuotationController::class, 'update'])->middleware('permission:quotations.edit');
            Route::patch('/{id}/status', [QuotationController::class, 'updateStatus'])->middleware('permission:quotations.edit');
            Route::delete('/{id}', [QuotationController::class, 'destroy'])->middleware('permission:quotations.delete');
        });

    });


    // Super Admin routes
    Route::prefix('super-admin')->middleware('super_admin')->group(function () {
        Route::get('/organisations',                [SuperAdminController::class, 'organisations']);
        Route::get('/organisations/{id}/users',     [SuperAdminController::class, 'orgUsers']);
        Route::patch('/organisations/{id}/toggle',  [SuperAdminController::class, 'toggleOrg']);
        Route::patch('/organisations/{id}/plan',    [SuperAdminController::class, 'changePlan']);
        Route::get('/users',                        [SuperAdminController::class, 'users']);
    });

});
