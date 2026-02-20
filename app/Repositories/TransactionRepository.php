<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\Budget;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function createBudget(array $data): Budget
    {
        return Budget::create($data);
    }

    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }

    public function findById(int $id): ?Transaction
    {
        return Transaction::where('id', $id)->where('user_id', Auth::id())->first();
    }

    public function getAllTransactions(array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::where('user_id', Auth::id())->with(['category', 'account']);

        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 1000);
    }

    public function update(int $id, array $data): bool
    {
        return Transaction::where('id', $id)->where('user_id', Auth::id())->update($data);
    }

    public function delete(int $id): bool
    {
        return Transaction::where('id', $id)->where('user_id', Auth::id())->delete();
    }

    /**
     * Find budget by category. If it does not exist, create a new one.
     */
    public function findBudgetByCategory(int $category_id): Budget
    {
        $user_id = Auth::id();
        $budget = Budget::where('user_id', $user_id)->where('category_id', $category_id)->first();

        if (!$budget) {
            // Create a new budget entry if it does not exist
            $budget = Budget::create([
                'user_id'       => $user_id,
                'category_id'   => $category_id,
                'budget_amount' => 0, // Default budget amount
                'total_amount'  => 0  // Default total amount
            ]);
        }

        return $budget;
    }

    /**
     * Update budget total amount
     */
    public function updateBudget(Budget $budget, float $amount): bool
    {
        return $budget->update(['total_amount' => $amount]);
    }

    public function findAccountById(int $accountId)
    {
        return Account::find($accountId);
    }

    public function findBudgetByCategoryAndMonth($userId, $categoryId, $month)
    {
        return Budget::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('month', $month)
            ->first();
    }
}
