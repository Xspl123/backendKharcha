<?php

namespace App\Repositories;

use App\Models\Budget;
use App\Repositories\Interfaces\BudgetRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class BudgetRepository implements BudgetRepositoryInterface
{
    public function create(array $data): Budget
    {
        return Budget::create($data);
    }

   
    public function getAll(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Budget::where('user_id', $userId)
            ->with(['category', 'account', 'user'])
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense');
            });

        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        // Month-wise filter
        if (!empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        return $query->paginate($filters['limit'] ?? 1000);
    }



    public function findById(int $userId, int $id): ?Budget
    {
        return Budget::where('user_id', $userId)->findOrFail($id);
    }

    public function update(int $categoryId, array $data): bool
    {
        $budget = Budget::where('category_id', $categoryId)
                        ->where('user_id', Auth::id())
                        ->first();

        if (!$budget) {
            return false;
        }

        $newAmount = $budget->budget_amount + $data['budget_amount'];

        return $budget->update(['budget_amount' => $newAmount]);
    }


    public function delete(int $userId, int $id): bool
    {
        $budget = Budget::where('user_id', $userId)->find($id);

        if (!$budget) {
            throw new ModelNotFoundException("Budget not found.");
        }

        return (bool) $budget->delete();
    }
}