<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Repositories\TransactionRepository;
use App\Repositories\Interfaces\BudgetRepositoryInterface;
use App\Repositories\BudgetRepository;
use App\Repositories\Interfaces\AccountRepositoryInterface;
use App\Repositories\AccountRepository;
use App\Repositories\Interfaces\ClientRepositoryInterface;
use App\Repositories\ClientRepository;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use App\Repositories\InvoiceRepository;
use App\Repositories\Interfaces\InvoicePaymentRepositoryInterface;
use App\Repositories\InvoicePaymentRepository;
use App\Repositories\Interfaces\ClientLedgerRepositoryInterface;
use App\Repositories\ClientLedgerRepository;
use App\Repositories\Interfaces\GstRepositoryInterface;
use App\Repositories\GstRepository;
use App\Repositories\Interfaces\VendorRepositoryInterface;
use App\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use App\Repositories\Interfaces\VendorPaymentRepositoryInterface;
use App\Repositories\VendorRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\VendorPaymentRepository;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\Interfaces\SalesReturnRepositoryInterface;
use App\Repositories\SalesReturnRepository;
use App\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use App\Repositories\PurchaseReturnRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(BudgetRepositoryInterface::class, BudgetRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(InvoicePaymentRepositoryInterface::class, InvoicePaymentRepository::class);
        $this->app->bind(ClientLedgerRepositoryInterface::class, ClientLedgerRepository::class);
        $this->app->bind(GstRepositoryInterface::class, GstRepository::class);
        $this->app->bind(VendorRepositoryInterface::class, VendorRepository::class);
        $this->app->bind(PurchaseOrderRepositoryInterface::class, PurchaseOrderRepository::class);
        $this->app->bind(VendorPaymentRepositoryInterface::class, VendorPaymentRepository::class);
        $this->app->bind(ProductCategoryRepositoryInterface::class, ProductCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class,ProductRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class,StockMovementRepository::class);
        $this->app->singleton(\App\Services\StockService::class);
        $this->app->bind(SalesReturnRepositoryInterface::class, SalesReturnRepository::class);
        $this->app->bind(PurchaseReturnRepositoryInterface::class, PurchaseReturnRepository::class);

        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
