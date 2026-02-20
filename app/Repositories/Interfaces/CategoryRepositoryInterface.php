<?php

namespace App\Repositories\Interfaces;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    public function create(array $data): Category;
    public function getAll(int $userId, ?string $search = null, int $page = 1, int $limit = 10): LengthAwarePaginator;
    public function findById(int $userId, int $id): ?Category;
    public function update(int $userId, int $id, array $data): bool;
    public function delete(int $userId, int $id): bool;
}
