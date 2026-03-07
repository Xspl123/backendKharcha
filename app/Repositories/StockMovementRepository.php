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

    // ── Get By Product ─────────────────────────────────────

    public function getByProduct(int $productId, array $filters): mixed
    {
        // Verify ownership
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

    // ── Get All ────────────────────────────────────────────

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

    // ── Create (Manual Movement) ───────────────────────────
    // FIXED: Array syntax instead of named parameters

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $product = Product::where('user_id', auth()->id())
                ->findOrFail($data['product_id']);

            // Out of stock check for outward movements
            $outwardTypes = ['sale_out', 'manual_out', 'return_out'];
            if (in_array($data['type'], $outwardTypes)) {
                abort_if(
                    $product->current_stock < (float) $data['qty'],
                    422,
                    "Insufficient stock. Available: {$product->current_stock} {$product->unit}"
                );
            }

            // FIXED: Use array instead of named parameters
            $movement = $this->stockService->addMovement([
                'product'   => $product,
                'type'      => $data['type'],
                'qty'       => (float) $data['qty'],
                'rate'      => (float) ($data['rate'] ?? $product->avg_cost),
                'reference' => [],
                'notes'     => $data['notes'] ?? '',
                'date'      => $data['movement_date'] ?? now()->toDateString(),
            ]);

            return $movement->load('product:id,name,sku,unit');
        });
    }

    // ── Delete ─────────────────────────────────────────────

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

            // Stock reverse karo
            $reverseQty = -$movement->qty; // opposite of original
            $product->current_stock = round($product->current_stock + $reverseQty, 4);
            $product->save();

            return $movement->delete();
        });
    }

    // ── Inventory Report ───────────────────────────────────

    public function getReport(array $filters): array
    {
        $products = Product::where('user_id', auth()->id())
            ->where('status', 'active')
            ->with('category:id,name,color')
            ->orderBy('name')
            ->get();

        // Category filter
        if (!empty($filters['category_id'])) {
            $products = $products->where('product_category_id', $filters['category_id']);
        }

        $totalValue   = $products->sum(fn($p) => $p->stock_value);
        $lowStock     = $products->filter(fn($p) => $p->isLowStock());
        $outOfStock   = $products->filter(fn($p) => $p->isOutOfStock());

        return [
            'summary' => [
                'total_products'    => $products->count(),
                'total_stock_value' => round($totalValue, 2),
                'low_stock_count'   => $lowStock->count(),
                'out_of_stock_count'=> $outOfStock->count(),
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
                'stock_value'     => $p->stock_value,
                'stock_status'    => $p->stock_status,
                'low_stock_alert' => $p->low_stock_alert,
                'selling_price'   => $p->selling_price,
            ])->values(),
        ];
    }
}