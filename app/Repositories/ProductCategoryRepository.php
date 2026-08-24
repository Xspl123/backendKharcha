<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use App\Repositories\Traits\OrgScope;

class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    use OrgScope;

    public function getAll(): mixed
    {
        return $this->scopeQuery(ProductCategory::query())
            ->withCount('products')->orderBy('name')->get();
    }

    public function getById(int $id): mixed
    {
        return $this->scopeQuery(ProductCategory::query())
            ->withCount('products')->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        return ProductCategory::create($this->scopeData($data));
    }

    public function update(int $id, array $data): mixed
    {
        $cat = $this->scopeQuery(ProductCategory::query())->findOrFail($id);
        $cat->update($data);
        return $cat->fresh();
    }

    public function delete(int $id): bool
    {
        $cat = $this->scopeQuery(ProductCategory::query())
            ->withCount('products')->findOrFail($id);
        abort_if($cat->products_count > 0, 422,
            'Category mein products hain. Pehle products ko alag category mein move karo.');
        return $cat->delete();
    }
}