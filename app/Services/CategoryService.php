<?php

namespace App\Services;

use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryService
{
    protected CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function createCategory(array $data): Category
    {
        return $this->categoryRepository->create($data);
    }

    public function getUserCategories(int $userId, ?string $search = null, int $page = 1, int $limit = 10): LengthAwarePaginator
    {
        return $this->categoryRepository->getAll($userId, $search, $page, $limit);
    }

    public function getCategoryById(int $userId, int $id): ?Category
    {
        return $this->categoryRepository->findById($userId, $id);
    }

    public function updateCategory(int $userId, int $id, array $data): bool
    {
        return $this->categoryRepository->update($userId, $id, $data);
    }

    public function deleteCategory(int $userId, int $id): bool
    {
        return $this->categoryRepository->delete($userId, $id);
    }
}
