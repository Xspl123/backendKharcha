<?php

namespace App\Repositories;

use App\Models\VendorPayment;
use App\Models\PurchaseOrder;
use App\Repositories\Interfaces\VendorPaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VendorPaymentRepository implements VendorPaymentRepositoryInterface
{
    // ── Get By Purchase Order ──────────────────────────────

    public function getByPurchaseOrder(int $purchaseOrderId): mixed
    {
        return VendorPayment::where('purchase_order_id', $purchaseOrderId)
            ->where('user_id', auth()->id())
            ->orderByDesc('payment_date')
            ->get();
    }

    // ── Get By Vendor ──────────────────────────────────────

    public function getByVendor(int $vendorId): mixed
    {
        return VendorPayment::where('vendor_id', $vendorId)
            ->where('user_id', auth()->id())
            ->with('purchaseOrder:id,po_number,total_amount')
            ->orderByDesc('payment_date')
            ->get();
    }

    // ── Create ─────────────────────────────────────────────

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::where('user_id', auth()->id())
                ->findOrFail($data['purchase_order_id']);

            // Validate — overpayment check
            $alreadyPaid = $po->paid_amount;
            $newTotal    = $alreadyPaid + (float) $data['amount'];

            abort_if(
                $newTotal > $po->total_amount,
                422,
                'Payment amount PO balance se zyada hai. Balance: ₹' .
                number_format($po->balance_amount, 2)
            );

            // Payment create karo
            $payment = VendorPayment::create([
                'user_id'           => auth()->id(),
                'vendor_id'         => $po->vendor_id,
                'purchase_order_id' => $po->id,
                'amount'            => $data['amount'],
                'payment_date'      => $data['payment_date'],
                'payment_method'    => $data['payment_method']  ?? 'bank_transfer',
                'reference_no'      => $data['reference_no']    ?? null,
                'notes'             => $data['notes']           ?? null,
            ]);

            // PO payment status update karo
            $po->updatePaymentStatus();

            return $payment->load('purchaseOrder:id,po_number,total_amount,paid_amount,balance_amount');
        });
    }

    // ── Delete ─────────────────────────────────────────────

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $payment = VendorPayment::where('user_id', auth()->id())->findOrFail($id);
            $po      = $payment->purchaseOrder;

            $payment->delete();

            // PO amounts recalculate karo
            $po->updatePaymentStatus();

            return true;
        });
    }
}