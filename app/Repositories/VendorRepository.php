<?php

namespace App\Repositories;

use App\Models\Vendor;
use App\Models\PurchaseOrder;
use App\Models\VendorPayment;
use App\Repositories\Interfaces\VendorRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;

class VendorRepository implements VendorRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters): mixed
    {
        $query = $this->scopeQuery(Vendor::query())
            ->withCount('purchaseOrders')
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) => $q
                ->where('vendor_name',  'like', "%{$s}%")
                ->orWhere('company_name','like', "%{$s}%")
                ->orWhere('email',       'like', "%{$s}%")
                ->orWhere('phone',       'like', "%{$s}%")
                ->orWhere('gstin',       'like', "%{$s}%")
            );
        }
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($this->resolvePerPage($filters));
    }

    public function getById(int $id): mixed
    {
        return $this->scopeQuery(Vendor::query())
            ->with([
                'purchaseOrders' => fn($q) => $q->latest()->take(5),
                'payments'       => fn($q) => $q->latest()->take(5),
            ])->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        $vendor = Vendor::create($this->scopeData($data));
        $this->bumpScopedCache(['vendors', 'purchase_orders']);
        return $vendor;
    }

    public function update(int $id, array $data): mixed
    {
        $vendor = $this->scopeQuery(Vendor::query())->findOrFail($id);
        $vendor->update($data);
        $this->bumpScopedCache(['vendors', 'purchase_orders']);
        return $vendor->fresh();
    }

    public function delete(int $id): bool
    {
        $vendor = $this->scopeQuery(Vendor::query())->findOrFail($id);
        abort_if(
            $vendor->purchaseOrders()->whereIn('status', ['pending','approved'])->count() > 0,
            422, 'Vendor ke active purchase orders hain.'
        );
        $this->bumpScopedCache(['vendors', 'purchase_orders']);
        return $vendor->delete();
    }

    public function getSummary(): array
  {
      return $this->rememberScoped('vendors', 'summary', 300, function () {
          $vendorCounts = $this->scopeQuery(Vendor::query())
              ->selectRaw('
                  COUNT(*) as total_vendors,
                  SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_vendors,
                  SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive_vendors
              ', ['active', 'inactive'])
              ->first();

          $poTotals = $this->scopeQuery(PurchaseOrder::query())
              ->selectRaw('
                  COALESCE(SUM(CASE WHEN status IN (?, ?) THEN total_amount ELSE 0 END), 0) as total_purchases,
                  COALESCE(SUM(CASE WHEN status IN (?, ?) THEN total_amount ELSE 0 END), 0) as returned_amount
              ', ['approved', 'received', 'returned', 'return'])
              ->first();

          $totalPurchases = round((float) $poTotals->total_purchases, 2);
          $returnedAmount = round((float) $poTotals->returned_amount, 2);
          $netPurchases   = round($totalPurchases - $returnedAmount, 2);
          $totalPaid      = round((float) $this->scopeQuery(VendorPayment::query())->sum('amount'), 2);
          $balance        = round(max($netPurchases - $totalPaid, 0), 2);
          $advancePaid    = round(max($totalPaid - $netPurchases, 0), 2);

          return [
              'total_vendors'    => (int) $vendorCounts->total_vendors,
              'active_vendors'   => (int) $vendorCounts->active_vendors,
              'inactive_vendors' => (int) $vendorCounts->inactive_vendors,

              'total_purchases'  => $totalPurchases,
              'returned_amount'  => $returnedAmount,
              'net_purchases'    => $netPurchases,
              'total_paid'       => $totalPaid,
              'total_balance'    => $balance,
              'advance_paid'     => $advancePaid,
          ];
      });
  }

}
