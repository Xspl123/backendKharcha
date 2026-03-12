<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    // ══════════════════════════════════════════════════════
    // ADD MOVEMENT — Generic method
    // StockMovementRepository yahan call karta hai
    // ══════════════════════════════════════════════════════

    public static function addMovement(array $data): StockMovement
    {
        // ✅ Product resolve — object ya product_id dono accept karo
        // StockMovementRepository 'product' => $obj pass karta hai
        // SalesReturnRepository    'product_id' => int pass karta hai
        $product   = null;
        $productId = null;

        if (!empty($data['product']) && $data['product'] instanceof Product) {
            $product   = $data['product'];
            $productId = $product->id;
        } elseif (!empty($data['product_id'])) {
            $productId = (int) $data['product_id'];
            $product   = Product::find($productId);
        }

        if (!$product || !$productId) {
            throw new \Exception('Valid product or product_id required for stock movement');
        }

        $type = $data['type'] ?? null;
        if (!$type) {
            throw new \Exception('Movement type required');
        }

        $qty  = (float) ($data['qty']  ?? 0);
        $rate = (float) ($data['rate'] ?? $product->avg_cost ?? 0);

        // ✅ stock_before — caller ne diya toh use karo, warna current stock
        $stockBefore = isset($data['stock_before'])
            ? (float) $data['stock_before']
            : (float) $product->current_stock;

        // ✅ stock_after — caller ne diya toh use karo, warna calculate karo
        if (isset($data['stock_after'])) {
            $stockAfter = (float) $data['stock_after'];
        } else {
            $inTypes  = ['purchase_in', 'return_in', 'opening', 'adjustment_plus', 'manual_in'];
            $outTypes = ['sale_out', 'adjustment_minus', 'manual_out', 'return_out'];

            if (in_array($type, $inTypes)) {
                $stockAfter = $stockBefore + $qty;
            } elseif (in_array($type, $outTypes)) {
                $stockAfter = max(0, $stockBefore - $qty);
            } else {
                $stockAfter = $stockBefore + $qty; // default +
            }
        }

        $value = (float) ($data['value'] ?? round($qty * $rate, 2));

        // ✅ reference — 'reference' array ya flat keys dono support
        $ref = $data['reference'] ?? [];
        $referenceType = $data['reference_type'] ?? $ref['type'] ?? null;
        $referenceId   = $data['reference_id']   ?? $ref['id']   ?? null;
        $referenceNo   = $data['reference_no']   ?? $ref['no']   ?? null;

        // ✅ movement_date — 'date' alias bhi accept karo (StockMovementRepository 'date' pass karta hai)
        $movementDate = $data['movement_date']
            ?? $data['date']
            ?? now()->toDateString();

        $userId = $data['user_id'] ?? auth()->id() ?? $product->user_id;

        $movement = StockMovement::create([
            'user_id'        => $userId,
            'product_id'     => $productId,
            'type'           => $type,
            'qty'            => $qty,
            'rate'           => $rate,
            'value'          => $value,
            'stock_before'   => $stockBefore,
            'stock_after'    => $stockAfter,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'reference_no'   => $referenceNo,
            'notes'          => $data['notes'] ?? '',
            'movement_date'  => $movementDate,
        ]);

        // ✅ Product current_stock update — stock_after se
        $product->update(['current_stock' => $stockAfter]);

        return $movement;
    }

    // ══════════════════════════════════════════════════════
    // INVOICE — Sale Out
    // ══════════════════════════════════════════════════════

    public static function recordSaleOut(array $items, int $userId, int $invoiceId, string $invoiceNo): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $qty       = (float) ($item['qty']  ?? 0);
            $rate      = (float) ($item['rate'] ?? 0);

            if (!$productId || $qty <= 0) continue;

            $product = Product::find($productId);
            if (!$product) continue;

            $stockBefore = (float) $product->current_stock;
            $stockAfter  = max(0, $stockBefore - $qty);

            StockMovement::create([
                'user_id'        => $userId,
                'product_id'     => $productId,
                'type'           => 'sale_out',
                'qty'            => $qty,
                'rate'           => $rate,
                'value'          => round($qty * $rate, 2),
                'stock_before'   => $stockBefore,
                'stock_after'    => $stockAfter,
                'reference_type' => 'invoice',
                'reference_id'   => $invoiceId,
                'reference_no'   => $invoiceNo,
                'notes'          => "Invoice #{$invoiceNo} — Sale",
                'movement_date'  => now()->toDateString(),
            ]);

            $product->update(['current_stock' => $stockAfter]);
        }
    }

    public static function reverseSaleOut(int $invoiceId, int $userId, string $invoiceNo): void
    {
        $movements = StockMovement::where('reference_type', 'invoice')
            ->where('reference_id', $invoiceId)
            ->where('type', 'sale_out')
            ->get();

        foreach ($movements as $movement) {
            $product = Product::find($movement->product_id);
            if (!$product) continue;

            $stockBefore = (float) $product->current_stock;
            $stockAfter  = $stockBefore + (float) $movement->qty;

            StockMovement::create([
                'user_id'        => $userId,
                'product_id'     => $movement->product_id,
                'type'           => 'return_in',
                'qty'            => $movement->qty,
                'rate'           => $movement->rate,
                'value'          => $movement->value,
                'stock_before'   => $stockBefore,
                'stock_after'    => $stockAfter,
                'reference_type' => 'invoice',
                'reference_id'   => $invoiceId,
                'reference_no'   => $invoiceNo,
                'notes'          => "Invoice #{$invoiceNo} — Sale Reversed",
                'movement_date'  => now()->toDateString(),
            ]);

            $product->update(['current_stock' => $stockAfter]);
        }

        StockMovement::where('reference_type', 'invoice')
            ->where('reference_id', $invoiceId)
            ->where('type', 'sale_out')
            ->delete();
    }

    // ══════════════════════════════════════════════════════
    // PURCHASE ORDER — Purchase In
    // PO received hone par:
    //   - Agar product_id linked → stock update karo
    //   - Agar product_id null   → auto product create karo phir stock update
    // ══════════════════════════════════════════════════════

    public function processPurchaseOrderReceived(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            foreach ($po->items as $item) {

                if ($item->qty <= 0) continue;

                // ── Step 1: Product resolve karo ─────────────
                $product = null;

                if ($item->product_id) {
                    // Already linked → directly use karo
                    $product = Product::where('user_id', auth()->id())
                        ->find($item->product_id);
                }

                if (!$product) {
                    // ── Auto Product Create ───────────────────
                    $product = $this->autoCreateProduct($item, $po);

                    // PO item mein product_id save karo (future reference)
                    $item->update(['product_id' => $product->id]);
                }

                // ── Step 2: Stock Movement ────────────────────
                $stockBefore = (float) $product->current_stock;
                $stockAfter  = $stockBefore + (float) $item->qty;

                // Weighted Average Cost update
                $oldValue   = $stockBefore * (float) $product->avg_cost;
                $newValue   = (float) $item->qty * (float) $item->rate;
                $totalQty   = $stockBefore + (float) $item->qty;
                $newAvgCost = $totalQty > 0
                    ? round(($oldValue + $newValue) / $totalQty, 2)
                    : (float) $item->rate;

                StockMovement::create([
                    'user_id'        => auth()->id(),
                    'product_id'     => $product->id,
                    'type'           => 'purchase_in',
                    'qty'            => $item->qty,
                    'rate'           => $item->rate,
                    'value'          => round($item->qty * $item->rate, 2),
                    'stock_before'   => $stockBefore,
                    'stock_after'    => $stockAfter,
                    'reference_type' => 'purchase_order',
                    'reference_id'   => $po->id,
                    'reference_no'   => $po->po_number,
                    'notes'          => "PO #{$po->po_number} — Purchase Received"
                        . ($stockBefore == 0 ? ' (Auto Created)' : ''),
                    'movement_date'  => $po->received_date ?? now()->toDateString(),
                ]);

                // ── Step 3: Product stock + avg_cost update ───
                $product->update([
                    'current_stock' => $stockAfter,
                    'avg_cost'      => $newAvgCost,
                ]);
            }
        });
    }

    // ══════════════════════════════════════════════════════
    // OPENING STOCK SET
    // Product ki opening stock set/update karo
    // ProductRepository 3 params pass karta hai:
    //   ($product, $newOpeningStock, $purchasePrice)
    // ══════════════════════════════════════════════════════

    public function setOpeningStock(
        Product $product,
        float   $newOpeningStock,
        float   $purchasePrice = 0.0   // ← 3rd param (optional, default 0)
    ): void {
        $oldOpeningStock = (float) $product->opening_stock;
        $diff            = $newOpeningStock - $oldOpeningStock;

        // Kuch change nahi hua — kuch mat karo
        if ($diff == 0) return;

        // Rate: purchasePrice > 0 use karo, warna product ki avg_cost
        $rate = $purchasePrice > 0 ? $purchasePrice : (float) $product->avg_cost;

        DB::transaction(function () use ($product, $newOpeningStock, $diff, $oldOpeningStock, $rate) {
            $stockBefore = (float) $product->current_stock;
            $stockAfter  = max(0, $stockBefore + $diff);

            StockMovement::create([
                'user_id'        => auth()->id() ?? $product->user_id,
                'product_id'     => $product->id,
                'type'           => 'opening',
                'qty'            => abs($diff),
                'rate'           => $rate,
                'value'          => round(abs($diff) * $rate, 2),
                'stock_before'   => $stockBefore,
                'stock_after'    => $stockAfter,
                'reference_type' => 'manual',
                'reference_id'   => $product->id,
                'reference_no'   => 'OPENING-ADJ',
                'notes'          => "Opening stock updated: {$oldOpeningStock} → {$newOpeningStock}",
                'movement_date'  => now()->toDateString(),
            ]);

            $product->update([
                'opening_stock' => $newOpeningStock,
                'current_stock' => $stockAfter,
                // avg_cost bhi update karo agar rate mila
                'avg_cost'      => $rate > 0 ? $rate : $product->avg_cost,
            ]);
        });
    }

    // ══════════════════════════════════════════════════════
    // INVENTORY SUMMARY
    // Dashboard stats ke liye
    // ══════════════════════════════════════════════════════

    public function getSummary(int $userId): array
    {
        $products = Product::where('user_id', $userId)->get();

        $totalProducts   = $products->count();
        $activeProducts  = $products->where('status', 'active')->count();
        $totalStockValue = $products->sum(fn($p) => $p->current_stock * $p->avg_cost);
        $lowStockCount   = $products->filter(fn($p) =>
            $p->low_stock_alert > 0 && $p->current_stock <= $p->low_stock_alert
        )->count();
        $outOfStockCount = $products->where('current_stock', '<=', 0)->count();
        $totalItems      = $products->sum('current_stock');

        return [
            'total_products'   => $totalProducts,
            'active_products'  => $activeProducts,
            'total_stock_value'=> round($totalStockValue, 2),
            'low_stock_count'  => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'total_items'      => $totalItems,
        ];
    }

    // ══════════════════════════════════════════════════════
    // LOW STOCK PRODUCTS
    // ══════════════════════════════════════════════════════

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

    // ── Auto Product Create ───────────────────────────────
    // PO item se naya product banao inventory mein

    private function autoCreateProduct($item, PurchaseOrder $po): Product
    {
        $userId = auth()->id();

        // SKU auto generate: PO-ITEMNAME-RANDOM
        $baseName = Str::upper(Str::slug(Str::limit($item->item_name, 8, ''), '-'));
        $sku      = 'PO-' . $baseName . '-' . strtoupper(Str::random(4));

        // Duplicate SKU avoid karo
        while (Product::where('user_id', $userId)->where('sku', $sku)->exists()) {
            $sku = 'PO-' . $baseName . '-' . strtoupper(Str::random(4));
        }

        // Selling price = purchase price * 1.2 (20% margin default)
        // User baad mein edit kar sakta hai
        $purchasePrice = (float) $item->rate;
        $sellingPrice  = round($purchasePrice * 1.20, 2);

        $product = Product::create([
            'user_id'        => $userId,
            'name'           => $item->item_name,
            'sku'            => $sku,
            'product_category_id'    => $item->category_id ?? null,
            'hsn_code'       => $item->hsn_code  ?? null,
            'unit'           => $item->unit       ?? 'pcs',
            'purchase_price' => $purchasePrice,
            'selling_price'  => $sellingPrice,
            'tax_rate'       => $item->tax_rate  ?? 18,
            'current_stock'  => 0,
            'opening_stock'  => (float) $item->qty,
            'avg_cost'       => $purchasePrice,
            'low_stock_alert'=> max(1, (int) ceil($item->qty * 0.2)),
            'status'         => 'active',
            'notes'          => "Auto created from PO #{$po->po_number} on " . now()->toDateString()
                . " | Selling price aur low stock alert update karo",
        ]);

        return $product;
    }
}