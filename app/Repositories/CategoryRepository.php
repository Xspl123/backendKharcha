<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function getAll(int $userId, ?string $search = null, int $page = 1, int $limit = 10): LengthAwarePaginator
    {
        return Category::where('user_id', $userId)
            ->when($search, fn($query) => $query->where('name', 'like', "%$search%"))
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }

    public function findById(int $userId, int $id): ?Category
    {
        return Category::where('user_id', $userId)->find($id);
    }

    public function update(int $userId, int $id, array $data): bool
    {
        return Category::where('user_id', $userId)->where('id', $id)->update($data);
    }

    public function delete(int $userId, int $id): bool
    {
        return Category::where('user_id', $userId)->where('id', $id)->delete();
    }
}
