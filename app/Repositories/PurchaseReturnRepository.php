<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use App\Repositories\StockMovementRepository;
use Illuminate\Support\Facades\DB;

class PurchaseReturnRepository implements PurchaseReturnRepositoryInterface
{
    public function __construct(
        private StockMovementRepository $stockRepo
    ) {}

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            $originalPO = PurchaseOrder::with('items.product')
                ->where('user_id', auth()->id())
                ->findOrFail($data['original_po_id']);

            $returnPO = new PurchaseOrder();
            $returnPO->user_id               = auth()->id();
            $returnPO->vendor_id             = $originalPO->vendor_id;
            $returnPO->po_number             = 'PR-' . $originalPO->po_number . '-' . time();
            $returnPO->po_date               = $data['return_date'] ?? now()->toDateString();
            $returnPO->expected_delivery_date= now()->toDateString();
            $returnPO->supply_type           = $originalPO->supply_type   ?? 'intra';
            $returnPO->place_of_supply       = $originalPO->place_of_supply ?? null;
            $returnPO->is_reverse_charge     = $originalPO->is_reverse_charge ?? 0;
            $returnPO->sub_total             = 0;
            $returnPO->cgst                  = 0;
            $returnPO->sgst                  = 0;
            $returnPO->igst                  = 0;
            $returnPO->total_amount          = 0;
            $returnPO->paid_amount           = 0;
            $returnPO->balance_amount        = 0;
            $returnPO->status                = 'return';
            $returnPO->is_return             = 1;                   
            $returnPO->original_po_id        = $originalPO->id;     
            $returnPO->notes                 = $data['notes'] ?? "Return of PO #{$originalPO->po_number}";
            $returnPO->save();
            $totalAmount = 0;
            $totalTax    = 0;

            foreach ($data['items'] as $item) {

                $originalItem = PurchaseOrderItem::where('purchase_order_id', $originalPO->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$originalItem) {
                    throw new \Exception(
                        "Item (Product ID: {$item['product_id']}) not found in PO #{$originalPO->po_number}"
                    );
                }

                $maxReturnable = (float) $originalItem->qty - (float) ($originalItem->returned_qty ?? 0);
                if ((float) $item['qty'] > $maxReturnable) {
                    throw new \Exception(
                        "Cannot return {$item['qty']} units of {$originalItem->item_name}. "
                        . "Max returnable: {$maxReturnable}"
                    );
                }

                $qty       = (float) $item['qty'];
                $rate      = (float) $item['rate'];
                $taxRate   = (float) ($originalItem->tax_rate ?? 0);
                $amount    = round($qty * $rate, 2);
                $taxAmount = round($amount * $taxRate / 100, 2);

                $totalAmount += $amount;
                $totalTax    += $taxAmount;
                $returnPO->items()->create([
                    'product_id'   => $item['product_id'],
                    'item_name'    => $originalItem->item_name,
                    'description'  => $item['reason'] ?? $originalItem->description ?? '',
                    'hsn_code'     => $originalItem->hsn_code ?? '',
                    'qty'          => $qty,
                    'unit'         => $originalItem->unit ?? 'pcs',
                    'rate'         => $rate,
                    'amount'       => $amount,
                    'tax_rate'     => $taxRate,
                    'tax_amount'   => $taxAmount,
                ]);

                \DB::table('purchase_order_items')
                    ->where('id', $originalItem->id)
                    ->update([
                        'returned_qty' => (float) $originalItem->returned_qty + $qty,
                        'is_returned'  => 1,
                        'updated_at'   => now(),
                    ]);

                $this->stockRepo->create([
                    'product_id'     => $item['product_id'],
                    'type'           => 'return_out',
                    'qty'            => $qty,
                    'rate'           => $rate,
                    'notes'          => "Purchase Return — PO #{$originalPO->po_number}"
                        . (isset($item['reason']) ? " — {$item['reason']}" : ''),
                    'movement_date'  => $data['return_date'] ?? now()->toDateString(),
                    'reference_type' => 'po_return',
                    'reference_id'   => $returnPO->id,
                    'reference_no'   => $returnPO->po_number,
                ]);
            }

            $isInter = ($originalPO->supply_type === 'inter');
            \DB::table('purchase_orders')
                ->where('id', $returnPO->id)
                ->update([
                    'sub_total'    => round($totalAmount, 2),
                    'cgst'         => $isInter ? 0 : round($totalTax / 2, 2),
                    'sgst'         => $isInter ? 0 : round($totalTax / 2, 2),
                    'igst'         => $isInter ? round($totalTax, 2) : 0,
                    'total_amount' => round($totalAmount + $totalTax, 2),
                    'updated_at'   => now(),
                ]);

            $allReturned = \DB::table('purchase_order_items')
                ->where('purchase_order_id', $originalPO->id)
                ->whereColumn('returned_qty', '<', 'qty')
                ->doesntExist();

            if ($allReturned) {
                \DB::table('purchase_orders')
                    ->where('id', $originalPO->id)
                    ->update(['status' => 'return', 'updated_at' => now()]);
            }

            $returnPO = $returnPO->fresh(['items.product', 'vendor']);
            $returnPO->setRelation('originalPO', $originalPO);

            return $returnPO;
        });
    }

    // ── Find ──────────────────────────────────────────────

    public function find(int $id)
    {
        return PurchaseOrder::with(['items.product', 'vendor', 'originalPO'])
            ->where('is_return', true)
            ->where('user_id', auth()->id())
            ->findOrFail($id);
    }

    // ── Get All ───────────────────────────────────────────

    public function getAll(array $filters = [])
    {
        $query = PurchaseOrder::with(['vendor', 'originalPO'])
            ->where('is_return', true)
            ->where('user_id', auth()->id());

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('po_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('po_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    // ── Get By PO ─────────────────────────────────────────

    public function getByPO(int $poId)
    {
        return PurchaseOrder::with(['items.product', 'vendor'])
            ->where('is_return', true)
            ->where('original_po_id', $poId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ── Update Status ─────────────────────────────────────

    public function updateStatus(int $id, string $status)
    {
        $return = PurchaseOrder::where('is_return', true)
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        $return->update(['status' => $status]);
        return $return;
    }
}