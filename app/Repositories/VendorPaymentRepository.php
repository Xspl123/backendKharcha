<?php

namespace App\Repositories;

use App\Models\VendorPayment;
use App\Models\PurchaseOrder;
use App\Repositories\Interfaces\VendorPaymentRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\DB;

class VendorPaymentRepository implements VendorPaymentRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getByPurchaseOrder(int $purchaseOrderId, array $filters = []): mixed
    {
        return $this->scopeQuery(VendorPayment::query())
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderByDesc('payment_date')
            ->paginate($this->resolvePerPage($filters, 50));
    }

    public function getByVendor(int $vendorId, array $filters = []): mixed
    {
        return $this->scopeQuery(VendorPayment::query())
            ->where('vendor_id', $vendorId)
            ->with('purchaseOrder:id,po_number,total_amount')
            ->orderByDesc('payment_date')
            ->paginate($this->resolvePerPage($filters, 50));
    }

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $po = $this->scopeQuery(PurchaseOrder::query())->findOrFail($data['purchase_order_id']);

            $newTotal = $po->paid_amount + (float) $data['amount'];
            abort_if($newTotal > $po->total_amount, 422,
                'Payment amount PO balance se zyada hai. Balance: ₹' . number_format($po->balance_amount, 2));

            $payment = VendorPayment::create($this->scopeData([
                'vendor_id'         => $po->vendor_id,
                'purchase_order_id' => $po->id,
                'amount'            => $data['amount'],
                'payment_date'      => $data['payment_date'],
                'payment_method'    => $data['payment_method'] ?? 'bank_transfer',
                'reference_no'      => $data['reference_no']   ?? null,
                'notes'             => $data['notes']          ?? null,
            ]));

            $po->updatePaymentStatus();

            $this->bumpScopedCache(['vendors', 'purchase_orders']);
            return $payment->load('purchaseOrder:id,po_number,total_amount,paid_amount,balance_amount');
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $payment = $this->scopeQuery(VendorPayment::query())->findOrFail($id);
            $po = $payment->purchaseOrder;
            $payment->delete();
            $po->updatePaymentStatus();
            $this->bumpScopedCache(['vendors', 'purchase_orders']);
            return true;
        });
    }
}
