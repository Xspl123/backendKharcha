<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(
        private StockService $stockService
    ) {}

    // ── Get By Product ────────────────────────────────────

    public function getByProduct(int $productId, array $filters): mixed
    {
        Product::where('user_id', auth()->id())->findOrFail($productId);

        $query = StockMovement::where('product_id', $productId)
            ->where('user_id', auth()->id())
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('movement_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('movement_date', '<=', $filters['to_date']);
        }

        return $query->get();
    }

    // ── Get All ───────────────────────────────────────────

    public function getAll(array $filters): mixed
    {
        $query = StockMovement::where('user_id', auth()->id())
            ->with('product:id,name,sku,unit')
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('movement_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('movement_date', '<=', $filters['to_date']);
        }

        return $query->limit(200)->get();
    }

    // ── Create (Manual Movement) ──────────────────────────

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {

            // ✅ FIX: product_id null check — sales return mein product_id
            //         null hone par stock movement skip karo
            $productId = $data['product_id'] ?? null;

            if (!$productId) {
                \Log::warning('StockMovementRepository::create — product_id null, skipping movement', [
                    'data' => $data,
                ]);
                return null;  // Stock movement nahi banega agar product nahi hai
            }

            // ✅ Product fetch — user ke scope mein
            $product = Product::where('user_id', auth()->id())
                ->find($productId);

            // ✅ FIX: findOrFail ki jagah find() — null handle karo
            if (!$product) {
                // Dusre user ka product ya exist nahi — skip
                \Log::warning("StockMovementRepository::create — Product #{$productId} not found for user " . auth()->id());
                return null;
            }

            $outwardTypes = ['sale_out', 'manual_out', 'return_out'];
            $inwardTypes  = ['purchase_in', 'manual_in', 'return_in', 'opening', 'adjustment_plus'];

            // Outward movement ke liye stock check
            if (in_array($data['type'], $outwardTypes)) {
                abort_if(
                    (float) $product->current_stock < (float) $data['qty'],
                    422,
                    "Insufficient stock. Available: {$product->current_stock} {$product->unit}"
                );
            }

            $oldStock = (float) $product->current_stock;
            $qty      = (float) $data['qty'];

            // ✅ stock_after calculate — addMovement pe chhod do yeh kaam
            if (in_array($data['type'], $inwardTypes)) {
                $newStock = $oldStock + $qty;
            } elseif (in_array($data['type'], $outwardTypes)) {
                $newStock = max(0, $oldStock - $qty);
            } else {
                $newStock = $oldStock + $qty; // default
            }

            // ✅ FIX: addMovement ko product object pass karo
            //         'stock_before' aur 'stock_after' bhi pass karo
            //         taaki addMovement dobara calculate na kare
            $movement = $this->stockService->addMovement([
                'product'        => $product,       // object pass karo
                'product_id'     => $product->id,   // backup
                'type'           => $data['type'],
                'qty'            => $qty,
                'rate'           => (float) ($data['rate'] ?? $product->avg_cost ?? 0),
                'stock_before'   => $oldStock,
                'stock_after'    => $newStock,       // ✅ pre-calculated
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id'   => $data['reference_id']   ?? null,
                'reference_no'   => $data['reference_no']   ?? null,
                'notes'          => $data['notes']          ?? '',
                'movement_date'  => $data['movement_date']  ?? $data['date'] ?? now()->toDateString(),
                'user_id'        => auth()->id(),
            ]);

            // ✅ FIX: addMovement already current_stock update karta hai
            //         yahan dobara update NAHI karo — double update bug tha
            // $product->update(['current_stock' => $newStock]); ← REMOVED

            if (!$movement) return null;

            return $movement->load('product:id,name,sku,unit');
        });
    }

    // ── Delete ────────────────────────────────────────────

    public function delete(int $id): bool
    {
        $movement = StockMovement::where('user_id', auth()->id())->findOrFail($id);

        abort_if(
            in_array($movement->type, ['purchase_in', 'sale_out']),
            422,
            'Auto-generated movements delete nahi ho sakte. PO ya Invoice se reverse karo.'
        );

        return DB::transaction(function () use ($movement) {
            $product = $movement->product;

            if ($product) {
                // Stock reverse karo
                $inwardTypes = ['purchase_in', 'return_in', 'opening', 'manual_in', 'adjustment_plus'];
                if (in_array($movement->type, $inwardTypes)) {
                    // Inward tha — delete hone par stock minus karo
                    $product->current_stock = max(0, (float) $product->current_stock - (float) $movement->qty);
                } else {
                    // Outward tha — delete hone par stock plus karo
                    $product->current_stock = (float) $product->current_stock + (float) $movement->qty;
                }
                $product->save();
            }

            return $movement->delete();
        });
    }

    // ── Inventory Report ──────────────────────────────────

    public function getReport(array $filters): array
    {
        $products = Product::where('user_id', auth()->id())
            ->where('status', 'active')
            ->with('category:id,name,color')
            ->orderBy('name')
            ->get();

        if (!empty($filters['category_id'])) {
            $products = $products->where('product_category_id', $filters['category_id']);
        }

        $totalValue = $products->sum(fn($p) => $p->current_stock * $p->avg_cost);
        $lowStock   = $products->filter(fn($p) =>
            $p->low_stock_alert > 0 && $p->current_stock <= $p->low_stock_alert
        );
        $outOfStock = $products->filter(fn($p) => $p->current_stock <= 0);

        return [
            'summary' => [
                'total_products'     => $products->count(),
                'total_stock_value'  => round($totalValue, 2),
                'low_stock_count'    => $lowStock->count(),
                'out_of_stock_count' => $outOfStock->count(),
            ],
            'products' => $products->map(fn($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'sku'             => $p->sku,
                'category'        => $p->category?->name,
                'category_color'  => $p->category?->color,
                'unit'            => $p->unit,
                'current_stock'   => $p->current_stock,
                'avg_cost'        => $p->avg_cost,
                'stock_value'     => round($p->current_stock * $p->avg_cost, 2),
                'stock_status'    => $p->current_stock <= 0
                    ? 'out_of_stock'
                    : ($p->low_stock_alert > 0 && $p->current_stock <= $p->low_stock_alert
                        ? 'low_stock' : 'in_stock'),
                'low_stock_alert' => $p->low_stock_alert,
                'selling_price'   => $p->selling_price,
            ])->values(),
        ];
    }
}