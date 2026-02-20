<?php

namespace App\Repositories\Interfaces;

use App\Models\Account;
use Illuminate\Pagination\LengthAwarePaginator;
interface AccountRepositoryInterface
{
    public function create(array $data): Account; 
    public function getAll(int $userId, ?string $search = null, int $page = 1, int $limit = 10): LengthAwarePaginator;
    public function findById(int $userId, int $id): ?Account;
    public function update(int $userId, int $id, array $data): bool;
    public function delete(int $userId, int $id): bool;
}
