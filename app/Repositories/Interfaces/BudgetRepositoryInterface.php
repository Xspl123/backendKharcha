<?php

namespace App\Repositories\Interfaces;

use App\Models\Budget;
use Illuminate\Pagination\LengthAwarePaginator;

interface BudgetRepositoryInterface
{
    public function create(array $data): Budget;
    public function getAll(int $userId, array $filters = []): LengthAwarePaginator;
    public function findById(int $userId, int $id): ?Budget;
    public function update(int $id, array $data): bool;
    public function delete(int $userId, int $id): bool;
}