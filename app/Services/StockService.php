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
    // ADD MOVEMENT - Generic method for all stock movements
    // ══════════════════════════════════════════════════════
    
    /**
     * Add a stock movement (generic method)
     */
    public static function addMovement(array $data): StockMovement
{
    // Validate required fields - More flexible validation
    if (!isset($data['product']) && !isset($data['product_id'])) {
        throw new \Exception("Either product or product_id is required");
    }
    
    if (!isset($data['type'])) {
        throw new \Exception("Movement type is required");
    }
    
    if (!isset($data['qty'])) {
        throw new \Exception("Quantity is required");
    }
    
    // Get product - handle both product object and product_id
    $product = null;
    $productId = null;
    
    if (isset($data['product']) && $data['product'] instanceof Product) {
        $product = $data['product'];
        $productId = $product->id;
    } elseif (isset($data['product_id'])) {
        $productId = $data['product_id'];
        $product = Product::find($productId);
    }
    
    if (!$product) {
        throw new \Exception("Product not found");
    }
    
    // Get rate - handle different possible keys
    $rate = $data['rate'] ?? 
            $data['price'] ?? 
            ($data['product'] ? $data['product']->avg_cost : 0) ?? 
            0;
    
    // Calculate stock before/after
    $stockBefore = (float) ($data['stock_before'] ?? $product->current_stock);
    $qty = (float) $data['qty'];
    $stockAfter = $stockBefore;
    
    switch ($data['type']) {
        case 'purchase_in':
        case 'return_in':
        case 'opening':
        case 'adjustment_plus':
            $stockAfter = $stockBefore + $qty;
            break;
            
        case 'sale_out':
        case 'adjustment_minus':
            $stockAfter = max(0, $stockBefore - $qty);
            break;
    }
    
    // Calculate value
    $value = $data['value'] ?? round($qty * (float) $rate, 2);
    
    // Handle reference data - could be array or separate fields
    $referenceType = null;
    $referenceId = null;
    $referenceNo = null;
    
    if (isset($data['reference']) && is_array($data['reference'])) {
        $referenceType = $data['reference']['type'] ?? null;
        $referenceId = $data['reference']['id'] ?? null;
        $referenceNo = $data['reference']['no'] ?? null;
    } else {
        $referenceType = $data['reference_type'] ?? null;
        $referenceId = $data['reference_id'] ?? null;
        $referenceNo = $data['reference_no'] ?? null;
    }
    
    // Get user_id
    $userId = $data['user_id'] ?? auth()->id() ?? $product->user_id;
    
    // Get notes
    $notes = $data['notes'] ?? '';
    
    // Get movement date
    $movementDate = $data['movement_date'] ?? $data['date'] ?? now()->toDateString();
    
    // Create stock movement
    $movement = StockMovement::create([
        'user_id'        => $userId,
        'product_id'     => $productId,
        'type'           => $data['type'],
        'qty'            => $qty,
        'rate'           => (float) $rate,
        'value'          => $value,
        'stock_before'   => $stockBefore,
        'stock_after'    => $stockAfter,
        'reference_type' => $referenceType,
        'reference_id'   => $referenceId,
        'reference_no'   => $referenceNo,
        'notes'          => $notes,
        'movement_date'  => $movementDate,
    ]);
    
    // Update product stock
    $product->update([
        'current_stock' => $stockAfter
    ]);
    
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

            // Use addMovement instead of direct create
            self::addMovement([
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

            // Use addMovement
            self::addMovement([
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

                    // PO item mein product_id save karo (future reference) - FIXED: array syntax
                    $item->update([
                        'product_id' => $product->id
                    ]);
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

                // Use addMovement instead of direct create
                self::addMovement([
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
                // FIXED: array syntax, not named parameters
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
        float   $purchasePrice = 0.0
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

            // Use addMovement
            self::addMovement([
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

            // FIXED: array syntax, not named parameters
            $product->update([
                'opening_stock' => $newOpeningStock,
                'current_stock' => $stockAfter,
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
        $purchasePrice = (float) $item->rate;
        $sellingPrice  = round($purchasePrice * 1.20, 2);

        $product = Product::create([
            'user_id'        => $userId,
            'name'           => $item->item_name,
            'sku'            => $sku,
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