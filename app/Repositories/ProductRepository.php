<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private StockService $stockService
    ) {}

    // ── Get All ────────────────────────────────────────────

    public function getAll(array $filters): mixed
    {
        $query = Product::where('user_id', auth()->id())
            ->with('category:id,name,color')
            ->orderBy('name');

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name',     'like', "%{$search}%")
                  ->orWhere('sku',      'like', "%{$search}%")
                  ->orWhere('hsn_code', 'like', "%{$search}%");
            });
        }

        // Status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Category
        if (!empty($filters['category_id'])) {
            $query->where('product_category_id', $filters['category_id']);
        }

        // Low stock only
        if (!empty($filters['low_stock'])) {
            $query->where('low_stock_alert', '>', 0)
                  ->whereColumn('current_stock', '<=', 'low_stock_alert');
        }

        // Out of stock
        if (!empty($filters['out_of_stock'])) {
            $query->where('current_stock', '<=', 0);
        }

        return $query->get();
    }

    // ── Get By ID ──────────────────────────────────────────

    public function getById(int $id): mixed
    {
        return Product::where('user_id', auth()->id())
            ->with([
                'category:id,name,color',
                'stockMovements' => fn($q) => $q->latest()->take(10),
            ])
            ->findOrFail($id);
    }

    // ── Create ─────────────────────────────────────────────

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $openingStock = (float) ($data['opening_stock'] ?? 0);
            $purchasePrice= (float) ($data['purchase_price'] ?? 0);

            $product = Product::create([
                ...$data,
                'user_id'       => auth()->id(),
                'current_stock' => $openingStock,
                'avg_cost'      => $purchasePrice,
            ]);

            // Opening stock movement create karo
            if ($openingStock > 0) {
                $this->stockService->setOpeningStock(
                    $product,
                    $openingStock,
                    $purchasePrice,
                );
            }

            return $product->load('category:id,name,color');
        });
    }

    // ── Update ─────────────────────────────────────────────

    public function update(int $id, array $data): mixed
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::where('user_id', auth()->id())->findOrFail($id);

            // Opening stock change hone par recalculate
            $newOpening = (float) ($data['opening_stock'] ?? $product->opening_stock);
            $oldOpening = (float) $product->opening_stock;

            $product->update($data);

            if ($newOpening !== $oldOpening) {
                $this->stockService->setOpeningStock(
                    $product,
                    $newOpening,
                    (float) ($data['purchase_price'] ?? $product->purchase_price),
                );
            }

            return $product->fresh('category:id,name,color');
        });
    }

    // ── Delete ─────────────────────────────────────────────

    public function delete(int $id): bool
    {
        $product = Product::where('user_id', auth()->id())->findOrFail($id);

        abort_if(
            $product->current_stock > 0,
            422,
            'Product ka stock exist karta hai. Pehle stock zero karo.'
        );

        return $product->delete();
    }

    // ── Summary ────────────────────────────────────────────

    public function getSummary(): array
    {
        return $this->stockService->getSummary(auth()->id());
    }

    // ── Low Stock ──────────────────────────────────────────

    public function getLowStock(): mixed
    {
        return $this->stockService->getLowStockProducts(auth()->id());
    }
}