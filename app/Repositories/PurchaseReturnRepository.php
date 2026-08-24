<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\DB;

class PurchaseReturnRepository implements PurchaseReturnRepositoryInterface
{
    use OrgScope, ScopedCache;

    public function __construct(private StockMovementRepository $stockRepo) {}

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $scopedData = $this->scopeData([]);

            $originalPO = $this->scopeQuery(PurchaseOrder::query())
                ->with('items.product')->findOrFail($data['original_po_id']);

            // ✅ Original PO received hona chahiye
            if ($originalPO->status !== 'received') {
                throw new \Exception('Only received POs can be returned.');
            }

            $returnPO                      = new PurchaseOrder();
            $returnPO->user_id             = $scopedData['user_id'];
            $returnPO->org_id              = $scopedData['org_id'] ?? null;
            $returnPO->vendor_id           = $originalPO->vendor_id;
            $returnPO->po_number           = 'PR-' . $originalPO->po_number . '-' . time();
            $returnPO->po_date             = $data['return_date'] ?? now()->toDateString();
            $returnPO->expected_delivery_date = now()->toDateString();
            $returnPO->supply_type         = $originalPO->supply_type    ?? 'intra';
            $returnPO->place_of_supply     = $originalPO->place_of_supply ?? null;
            $returnPO->is_reverse_charge   = $originalPO->is_reverse_charge ?? 0;
            $returnPO->sub_total           = 0;
            $returnPO->cgst                = 0;
            $returnPO->sgst                = 0;
            $returnPO->igst                = 0;
            $returnPO->total_amount        = 0;
            $returnPO->paid_amount         = 0;
            $returnPO->balance_amount      = 0;
            $returnPO->status              = 'return';
            $returnPO->is_return           = 1;
            $returnPO->original_po_id      = $originalPO->id;
            $returnPO->notes               = $data['notes'] ?? "Return of PO #{$originalPO->po_number}";
            $returnPO->save();

            $totalAmount = 0;
            $totalTax    = 0;

            foreach ($data['items'] as $item) {
                $originalItemQuery = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $originalPO->id)
                    ->where('is_return_item', false);

                if (!empty($item['purchase_order_item_id'])) {
                    $originalItemQuery->where('id', $item['purchase_order_item_id']);
                } else {
                    $originalItemQuery->where('product_id', $item['product_id']);
                }

                $originalItem = $originalItemQuery->first();

                if (!$originalItem) {
                    throw new \Exception('Selected item not found in the original purchase order.');
                }

                $maxReturnable = (float) $originalItem->qty - (float) ($originalItem->returned_qty ?? 0);
                if ((float) $item['qty'] > $maxReturnable) {
                    throw new \Exception("Cannot return {$item['qty']} units. Max returnable: {$maxReturnable}");
                }

                $qty       = (float) $item['qty'];
                $rate      = (float) $item['rate'];
                $taxRate   = (float) ($originalItem->tax_rate ?? 0);
                $amount    = round($qty * $rate, 2);
                $taxAmount = round($amount * $taxRate / 100, 2);
                $newReturnedQty = round((float) ($originalItem->returned_qty ?? 0) + $qty, 2);
                $isFullyReturned = $newReturnedQty >= round((float) $originalItem->qty, 2);

                $totalAmount += $amount;
                $totalTax    += $taxAmount;

                $returnPO->items()->create([
                    'user_id'     => $scopedData['user_id'],
                    'org_id'      => $scopedData['org_id'] ?? null,
                    'product_id'  => $originalItem->product_id,
                    'category_id' => $originalItem->category_id,
                    'item_name'   => $originalItem->item_name,
                    'description' => $item['reason'] ?? $originalItem->description ?? '',
                    'hsn_code'    => $originalItem->hsn_code ?? '',
                    'qty'         => $qty,
                    'unit'        => $originalItem->unit ?? 'pcs',
                    'rate'        => $rate,
                    'amount'      => $amount,
                    'tax_rate'    => $taxRate,
                    'tax_amount'  => $taxAmount,
                    'returned_qty'=> 0,
                    'is_returned' => false,
                    'original_item_id' => $originalItem->id,
                    'is_return_item' => true,
                ]);

                DB::table('purchase_order_items')->where('id', $originalItem->id)->update([
                    'returned_qty' => $newReturnedQty,
                    'is_returned'  => $isFullyReturned,
                    'updated_at'   => now(),
                ]);

                $this->stockRepo->create([
                    'product_id'     => $originalItem->product_id,
                    'type'           => 'return_out',
                    'qty'            => $qty,
                    'rate'           => $rate,
                    'org_id'         => $scopedData['org_id'] ?? null,
                    'notes'          => "Purchase Return — PO #{$originalPO->po_number}" .
                                        (isset($item['reason']) ? " — {$item['reason']}" : ''),
                    'movement_date'  => $data['return_date'] ?? now()->toDateString(),
                    'reference_type' => 'po_return',
                    'reference_id'   => $returnPO->id,
                    'reference_no'   => $returnPO->po_number,
                ]);
            }

            $isInter = ($originalPO->supply_type === 'inter');
            DB::table('purchase_orders')->where('id', $returnPO->id)->update([
                'sub_total'    => round($totalAmount, 2),
                'cgst'         => $isInter ? 0 : round($totalTax / 2, 2),
                'sgst'         => $isInter ? 0 : round($totalTax / 2, 2),
                'igst'         => $isInter ? round($totalTax, 2) : 0,
                'total_amount' => round($totalAmount + $totalTax, 2),
                'updated_at'   => now(),
            ]);

            $originalPO->refresh();
            $originalPO->recalculateAmountsFromRemainingItems();

            // Original PO ko sirf tab return mark karo jab sari quantities fully return ho chuki hon.
            $allReturned = DB::table('purchase_order_items')
                ->where('purchase_order_id', $originalPO->id)
                ->where('is_return_item', false)
                ->whereColumn('returned_qty', '<', 'qty')
                ->doesntExist();

            if ($allReturned) {
                // Sab items return ho gaye → original PO = return
                DB::table('purchase_orders')->where('id', $originalPO->id)
                    ->update(['status' => 'return', 'updated_at' => now()]);
            }
            // Partial return hone pe original PO status 'received' hi rahega

            $this->bumpScopedCache(['purchase_orders', 'vendors', 'stock', 'stock_report']);
            return $returnPO->fresh(['items.product', 'vendor', 'originalPO']);
        });
    }

    public function find(int $id)
    {
        return $this->scopeQuery(PurchaseOrder::query())
            ->with(['items.product', 'vendor', 'originalPO'])
            ->where('is_return', true)->findOrFail($id);
    }

    public function getAll(array $filters = [])
    {
        $query = $this->scopeQuery(PurchaseOrder::query())
            ->with(['vendor', 'originalPO'])->where('is_return', true);

        if (!empty($filters['vendor_id'])) $query->where('vendor_id', $filters['vendor_id']);
        if (!empty($filters['from_date'])) $query->whereDate('po_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date']))   $query->whereDate('po_date', '<=', $filters['to_date']);

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    public function getByPO(int $poId)
    {
        return $this->scopeQuery(PurchaseOrder::query())
            ->with(['items.product', 'vendor'])
            ->where('is_return', true)
            ->where('original_po_id', $poId)
            ->orderBy('created_at', 'desc')->get();
    }

    public function updateStatus(int $id, string $status)
    {
        $return = $this->scopeQuery(PurchaseOrder::query())
            ->where('is_return', true)->findOrFail($id);
        $return->update(['status' => $status]);
        $this->bumpScopedCache(['purchase_orders', 'vendors']);
        return $return;
    }
}
