<?php

namespace App\Repositories\Interfaces;

use App\Models\Transaction;
use App\Models\Budget;
use App\Models\Account;
use Illuminate\Pagination\LengthAwarePaginator;

interface TransactionRepositoryInterface
{
    public function create(array $data): Transaction;
    public function findById(int $id): ?Transaction;
    public function getAllTransactions(array $filters = []): LengthAwarePaginator;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function findBudgetByCategory(int $categoryId): Budget;
    public function updateBudget(Budget $budget, float $amount): bool;
    public function createBudget(array $data): Budget;
    public function findAccountById(int $accountId);
}
