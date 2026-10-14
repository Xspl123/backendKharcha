<?php

namespace App\Repositories;

use App\Models\VendorPayment;
use App\Models\OrgAccount;
use App\Models\OrgTransaction;
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
                'account_id'        => $data['account_id']     ?? null,
                'amount'            => $data['amount'],
                'payment_date'      => $data['payment_date'],
                'payment_method'    => $data['payment_method'] ?? 'bank_transfer',
                'reference_no'      => $data['reference_no']   ?? null,
                'notes'             => $data['notes']          ?? null,
            ]));

            // 👇 Auto-debit Org Account & Auto-record in Cashbook
            if (!empty($data['account_id'])) {
                $account = OrgAccount::find($data['account_id']);
                if ($account) {
                    $amount = (float) $data['amount'];
                    $account->decrement('current_balance', $amount);
                    OrgTransaction::create($this->scopeData([
                        'account_id'        => $account->id,
                        'transaction_date'  => $payment->payment_date,
                        'type'              => 'debit',
                        'category'          => 'vendor_payment',
                        'amount'            => $amount,
                        'balance_after'     => (float) $account->fresh()->current_balance,
                        'payment_method'    => $payment->payment_method ?? 'bank_transfer',
                        'reference_no'      => $payment->reference_no ?? null,
                        'description'       => 'Vendor Payment: ' . ($po->vendor?->vendor_name ?? 'Vendor') . ' (PO: ' . $po->po_number . ')',
                        'vendor_payment_id' => $payment->id,
                    ]));
                }
            }

            $po->updatePaymentStatus();

            $this->bumpScopedCache(['vendors', 'purchase_orders', 'org_accounts', 'org_transactions']);
            return $payment->load('purchaseOrder:id,po_number,total_amount,paid_amount,balance_amount');
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $payment = $this->scopeQuery(VendorPayment::query())->findOrFail($id);
            $po = $payment->purchaseOrder;

            // Agar payment delete ho to Org Account me paise wapas credit ho jayein
            if ($payment->account_id) {
                $account = OrgAccount::find($payment->account_id);
                if ($account) {
                    $account->increment('current_balance', (float) $payment->amount);
                    OrgTransaction::where('vendor_payment_id', $payment->id)->delete();
                }
            }

            $payment->delete();
            $po->updatePaymentStatus();
            $this->bumpScopedCache(['vendors', 'purchase_orders', 'org_accounts', 'org_transactions']);
            return true;
        });
    }
}