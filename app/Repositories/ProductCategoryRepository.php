<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;

class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function getAll(): mixed
    {
        return ProductCategory::where('user_id', auth()->id())
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function getById(int $id): mixed
    {
        return ProductCategory::where('user_id', auth()->id())
            ->withCount('products')
            ->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        return ProductCategory::create([
            ...$data,
            'user_id' => auth()->id(),
        ]);
    }

    public function update(int $id, array $data): mixed
    {
        $category = ProductCategory::where('user_id', auth()->id())
            ->findOrFail($id);
        $category->update($data);
        return $category->fresh();
    }

    public function delete(int $id): bool
    {
        $category = ProductCategory::where('user_id', auth()->id())
            ->withCount('products')
            ->findOrFail($id);

        abort_if(
            $category->products_count > 0,
            422,
            'Category mein products hain. Pehle products ko alag category mein move karo.'
        );

        return $category->delete();
    }
}