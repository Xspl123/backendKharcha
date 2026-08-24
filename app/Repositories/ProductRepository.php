<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function __construct(private StockService $stockService) {}

    public function getAll(array $filters): mixed
    {
        $query = $this->scopeQuery(Product::query())
            ->with('category:id,name,color')
            ->with([
                'attributeValues.attribute:id,name,group_id',
            ])
            ->withCount('attributeValues')
            ->orderBy('name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name',     'like', "%{$search}%")
                  ->orWhere('sku',      'like', "%{$search}%")
                  ->orWhere('hsn_code', 'like', "%{$search}%");
            });
        }
        if (!empty($filters['status']))      $query->where('status', $filters['status']);
        if (!empty($filters['category_id'])) $query->where('product_category_id', $filters['category_id']);
        if (!empty($filters['low_stock']))   $query->where('low_stock_alert', '>', 0)->whereColumn('current_stock', '<=', 'low_stock_alert');
        if (!empty($filters['out_of_stock'])) $query->where('current_stock', '<=', 0);

        return $query->paginate($this->resolvePerPage($filters));
    }

    public function getById(int $id): mixed
    {
        return $this->scopeQuery(Product::query())
            ->with([
                'category:id,name,color',
                'stockMovements' => fn($q) => $q->latest()->take(10),
                'attributeValues.attribute',
                'attributes',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $openingStock  = (float) ($data['opening_stock']  ?? 0);
            $purchasePrice = (float) ($data['purchase_price'] ?? 0);

            $product = Product::create([
                ...$this->scopeData($data),
                'current_stock' => $openingStock,
                'avg_cost'      => $purchasePrice,
            ]);

            if ($openingStock > 0) {
                $this->stockService->setOpeningStock($product, $openingStock, $purchasePrice);
            }

            $this->bumpScopedCache(['products', 'stock', 'stock_report']);

            return $product->load('category:id,name,color');
        });
    }

    public function update(int $id, array $data): mixed
    {
        return DB::transaction(function () use ($id, $data) {
            $product    = $this->scopeQuery(Product::query())->findOrFail($id);
            $newOpening = (float) ($data['opening_stock'] ?? $product->opening_stock);
            $oldOpening = (float) $product->opening_stock;

            $product->update($data);

            if ($newOpening !== $oldOpening) {
                $this->stockService->setOpeningStock(
                    $product, $newOpening,
                    (float) ($data['purchase_price'] ?? $product->purchase_price),
                );
            }

            $this->bumpScopedCache(['products', 'stock', 'stock_report']);

            return $product->fresh('category:id,name,color');
        });
    }

    public function delete(int $id): bool
    {
        $product = $this->scopeQuery(Product::query())->findOrFail($id);
        abort_if($product->current_stock > 0, 422, 'Product ka stock exist karta hai. Pehle stock zero karo.');
        $this->bumpScopedCache(['products', 'stock', 'stock_report']);
        return $product->delete();
    }

    public function getSummary(): array
    {
        return $this->rememberScoped('products', 'summary', 300, fn () =>
            $this->stockService->getSummary($this->userId(), $this->orgId())
        );
    }

    public function getLowStock(): mixed
    {
        return $this->rememberScoped('products', 'low_stock', 180, fn () =>
            $this->stockService->getLowStockProducts($this->userId(), $this->orgId())
        );
    }
}
