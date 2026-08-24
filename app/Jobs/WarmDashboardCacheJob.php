<?php

namespace App\Jobs;

use App\Models\User;
use App\Repositories\Interfaces\GstRepositoryInterface;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use App\Repositories\Interfaces\VendorRepositoryInterface;
use App\Repositories\StockMovementRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class WarmDashboardCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly int $userId)
    {
        $this->onQueue('default');
    }

    public function handle(
        ProductRepositoryInterface $products,
        PurchaseOrderRepositoryInterface $purchaseOrders,
        VendorRepositoryInterface $vendors,
        LeadRepositoryInterface $leads,
        GstRepositoryInterface $gst,
        StockMovementRepository $stockMovements,
    ): void {
        $user = User::find($this->userId);

        if (!$user || !$user->is_active) {
            return;
        }

        Auth::setUser($user);

        $products->getSummary();
        $products->getLowStock();
        $purchaseOrders->getSummary();
        $vendors->getSummary();
        $leads->getSummary();
        $leads->getPipelineStats();
        $stockMovements->getReport([]);
        $gst->getSummary(now()->format('Y-m'));

        Auth::forgetGuards();
    }
}
