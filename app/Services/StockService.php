<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class StockService
{
    // ── Add Stock Movement ────────────────────────────────

    public function addMovement(
        Product $product,
        string  $type,
        float   $qty,
        float   $rate = 0,
        array   $reference = [],
        string  $notes = '',
        ?string $date = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $product, $type, $qty, $rate, $reference, $notes, $date
        ) {
            $stockBefore = $product->current_stock;

            // Inward movements → positive qty
            // Outward movements → negative qty
            $isOutward = in_array($type, ['sale_out', 'manual_out', 'return_out']);
            $actualQty = $isOutward ? -abs($qty) : abs($qty);

            // Avg cost update — sirf inward pe
            if (!$isOutward && $rate > 0 && abs($qty) > 0) {
                $product->updateAvgCost(abs($qty), $rate);
            }

            // Stock update
            $product->current_stock = round($stockBefore + $actualQty, 4);
            $product->save();

            // Movement record
            $movement = StockMovement::create([
                'user_id'        => auth()->id(),
                'product_id'     => $product->id,
                'type'           => $type,
                'qty'            => $actualQty,
                'rate'           => $rate,
                'value'          => round(abs($qty) * $rate, 2),
                'stock_before'   => $stockBefore,
                'stock_after'    => $product->current_stock,
                'reference_type' => $reference['type'] ?? null,
                'reference_id'   => $reference['id']   ?? null,
                'reference_no'   => $reference['no']   ?? null,
                'notes'          => $notes,
                'movement_date'  => $date ?? now()->toDateString(),
            ]);

            return $movement;
        });
    }

    // ── PO Received → Auto Stock Update ──────────────────

    public function processPurchaseOrderReceived(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            foreach ($po->items as $item) {
                // Sirf wahi items jinka product_id linked hai
                if (!$item->product_id) continue;

                $product = Product::where('user_id', auth()->id())
                    ->find($item->product_id);

                if (!$product) continue;

                $this->addMovement(
                    product:   $product,
                    type:      'purchase_in',
                    qty:       $item->qty,
                    rate:      $item->rate,
                    reference: [
                        'type' => 'purchase_order',
                        'id'   => $po->id,
                        'no'   => $po->po_number,
                    ],
                    notes: "Auto stock in from PO: {$po->po_number}",
                    date:  $po->received_date?->toDateString() ?? now()->toDateString(),
                );
            }
        });
    }

    // ── Opening Stock Set ─────────────────────────────────

    public function setOpeningStock(Product $product, float $qty, float $rate): void
    {
        // Pehle koi opening entry hai toh delete karo
        StockMovement::where('product_id', $product->id)
            ->where('type', 'opening')
            ->delete();

        // Reset stock
        $product->current_stock = 0;
        $product->avg_cost      = 0;
        $product->save();

        if ($qty > 0) {
            $this->addMovement(
                product: $product,
                type:    'opening',
                qty:     $qty,
                rate:    $rate,
                notes:   'Opening stock entry',
                date:    now()->toDateString(),
            );
        }
    }

    // ── Low Stock Products ────────────────────────────────

    public function getLowStockProducts(int $userId): mixed
    {
        return Product::where('user_id', $userId)
            ->where('status', 'active')
            ->where('low_stock_alert', '>', 0)
            ->whereColumn('current_stock', '<=', 'low_stock_alert')
            ->with('category:id,name,color')
            ->orderBy('current_stock')
            ->get();
    }

    // ── Inventory Summary ─────────────────────────────────

    public function getSummary(int $userId): array
    {
        $products = Product::where('user_id', $userId)
            ->where('status', 'active')
            ->get();

        $lowStock  = $products->filter(fn($p) => $p->isLowStock());
        $outStock  = $products->filter(fn($p) => $p->isOutOfStock());
        $totalVal  = $products->sum(fn($p) => $p->stock_value);

        return [
            'total_products'   => $products->count(),
            'in_stock'         => $products->filter(fn($p) => !$p->isLowStock() && !$p->isOutOfStock())->count(),
            'low_stock'        => $lowStock->count(),
            'out_of_stock'     => $outStock->count(),
            'total_stock_value'=> round($totalVal, 2),
            'low_stock_items'  => $lowStock->map(fn($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'sku'           => $p->sku,
                'current_stock' => $p->current_stock,
                'low_stock_alert'=> $p->low_stock_alert,
                'unit'          => $p->unit,
            ])->values(),
        ];
    }
}