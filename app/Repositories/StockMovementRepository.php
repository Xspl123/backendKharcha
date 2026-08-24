<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class StockMovementRepository implements StockMovementRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function __construct(private StockService $stockService) {}

    public function getByProduct(int $productId, array $filters): mixed
    {
        $this->scopeQuery(Product::query())->findOrFail($productId);

        $query = $this->scopeQuery(StockMovement::query())
            ->where('product_id', $productId)
            ->orderByDesc('movement_date')->orderByDesc('id');

        if (!empty($filters['type']))      $query->where('type', $filters['type']);
        if (!empty($filters['from_date'])) $query->whereDate('movement_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date']))   $query->whereDate('movement_date', '<=', $filters['to_date']);

        return $query->paginate($this->resolvePerPage($filters, 50));
    }

    public function getAll(array $filters): mixed
    {
        $query = $this->scopeQuery(StockMovement::query())
            ->with('product:id,name,sku,unit')
            ->orderByDesc('movement_date')->orderByDesc('id');

        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (!empty($filters['type']))       $query->where('type', $filters['type']);
        if (!empty($filters['from_date']))  $query->whereDate('movement_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date']))    $query->whereDate('movement_date', '<=', $filters['to_date']);

        return $query->paginate($this->resolvePerPage($filters, 50));
    }

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $productId = $data['product_id'] ?? null;
            if (!$productId) return null;

            $product = $this->scopeQuery(Product::query())->find($productId);
            if (!$product) return null;

            $outwardTypes = ['sale_out', 'manual_out', 'return_out'];
            $inwardTypes  = ['purchase_in', 'manual_in', 'return_in', 'opening', 'adjustment_plus'];

            if (in_array($data['type'], $outwardTypes)) {
                abort_if((float) $product->current_stock < (float) $data['qty'], 422,
                    "Insufficient stock. Available: {$product->current_stock} {$product->unit}");
            }

            $oldStock = (float) $product->current_stock;
            $qty      = (float) $data['qty'];
            $newStock = in_array($data['type'], $inwardTypes)
                ? $oldStock + $qty
                : max(0, $oldStock - $qty);

            $movement = $this->stockService->addMovement([
                'product'        => $product,
                'product_id'     => $product->id,
                'type'           => $data['type'],
                'qty'            => $qty,
                'rate'           => (float) ($data['rate'] ?? $product->avg_cost ?? 0),
                'stock_before'   => $oldStock,
                'stock_after'    => $newStock,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id'   => $data['reference_id']   ?? null,
                'reference_no'   => $data['reference_no']   ?? null,
                'notes'          => $data['notes']          ?? '',
                'movement_date'  => $data['movement_date']  ?? now()->toDateString(),
                'user_id'        => $this->userId(),
                'org_id'         => $this->orgId(),  // ← NEW
            ]);

            $this->bumpScopedCache(['stock', 'stock_report', 'products']);
            return $movement?->load('product:id,name,sku,unit');
        });
    }

    public function delete(int $id): bool
    {
        $movement = $this->scopeQuery(StockMovement::query())->findOrFail($id);
        abort_if(in_array($movement->type, ['purchase_in', 'sale_out']), 422,
            'Auto-generated movements delete nahi ho sakte.');

        return DB::transaction(function () use ($movement) {
            $product = $movement->product;
            if ($product) {
                $inward = ['purchase_in','return_in','opening','manual_in','adjustment_plus'];
                $product->current_stock = in_array($movement->type, $inward)
                    ? max(0, $product->current_stock - $movement->qty)
                    : $product->current_stock + $movement->qty;
                $product->save();
            }
            $this->bumpScopedCache(['stock', 'stock_report', 'products']);
            return $movement->delete();
        });
    }

    public function getReport(array $filters): array
    {
        $suffix = 'report:' . md5(json_encode($filters));

        return $this->rememberScoped('stock_report', $suffix, 180, function () use ($filters) {
            $query = $this->scopeQuery(Product::query())
                ->where('status', 'active')
                ->with('category:id,name,color')
                ->orderBy('name');

            if (!empty($filters['category_id'])) {
                $query->where('product_category_id', $filters['category_id']);
            }

            $products = $query->get();
            $totalValue = $products->sum(fn($p) => $p->current_stock * $p->avg_cost);

            return [
                'summary' => [
                    'total_products'     => $products->count(),
                    'total_stock_value'  => round($totalValue, 2),
                    'low_stock_count'    => $products->filter(fn($p) => $p->low_stock_alert > 0 && $p->current_stock <= $p->low_stock_alert)->count(),
                    'out_of_stock_count' => $products->filter(fn($p) => $p->current_stock <= 0)->count(),
                ],
                'products' => $products->map(fn($p) => [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'sku'            => $p->sku,
                    'category'       => $p->category?->name,
                    'category_color' => $p->category?->color,
                    'unit'           => $p->unit,
                    'current_stock'  => $p->current_stock,
                    'avg_cost'       => $p->avg_cost,
                    'stock_value'    => round($p->current_stock * $p->avg_cost, 2),
                    'stock_status'   => $p->current_stock <= 0 ? 'out_of_stock'
                        : ($p->low_stock_alert > 0 && $p->current_stock <= $p->low_stock_alert ? 'low_stock' : 'in_stock'),
                ])->values(),
            ];
        });
    }
}
